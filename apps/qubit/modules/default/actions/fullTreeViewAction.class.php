<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Action Handler for FullWidth TreeView
 *
 * @package AccesstoMemory
 * @subpackage model
 * @author Andy Koch <koch.andy@gmail.com>
 */
class DefaultFullTreeViewAction extends sfAction
{
  public function execute($request)
  {
    ProjectConfiguration::getActive()->loadHelpers('Qubit');

    // Get show identifier setting to prepare the reference code if necessary
    $this->showIdentifier = sfConfig::get('app_treeview_show_identifier', 'no');

    // Set response to JSON
    $this->getResponse()->setContentType('application/json');
  }

  protected function getNodeOrChildrenNodes($id, $baseReferenceCode, $children = false, $options = array())
  {
    $i18n = sfContext::getInstance()->i18n;
    $culture = $this->getUser()->getCulture();

    // Remove drafts for unauthenticated users
    $draftsSql = !$this->getUser()->user ? 'AND status.status_id <> ' . QubitTerm::PUBLICATION_STATUS_DRAFT_ID : '' ;

    // Get current node data (for the collection root on the first load) or children data
    $condition = $children ? 'io.parent_id = :id' : 'io.id = :id' ;

    // Determine ordering
    $orderColumn = (isset($options['orderColumn'])) ? $options['orderColumn'] : 'io.lft';

    // If we're currently fetching children and paging options are set,
    // use paging options to assemble LIMIT clause
    $limitClause = "";
    $skip = (isset($options['skip'])) ? $options['skip'] : null;
    $limit = (isset($options['limit'])) ? $options['limit'] : null;

    if ($children && (ctype_digit($skip) || ctype_digit($limit)))
    {
      $limitClause = "LIMIT ";
      $limitClause .= (ctype_digit($skip)) ? $skip : "0";
      $limitClause .= (ctype_digit($limit)) ? ", ". $limit : "";
    }

    $sql = "SELECT SQL_CALC_FOUND_ROWS
      io.id, io.lft, io.rgt,
      io.source_culture,
      io.identifier,
      current_i18n.title AS current_title,
      io.parent_id AS parent,
      slug.slug,
      IFNULL(lod.name, '') AS lod,
      st_i18n.name AS status,
      status.status_id AS status_id
      FROM
        information_object io
        LEFT JOIN information_object_i18n current_i18n ON io.id = current_i18n.id AND current_i18n.culture = :culture
        LEFT JOIN term_i18n lod ON io.level_of_description_id = lod.id AND lod.culture = :culture
        LEFT JOIN status ON io.id = status.object_id AND status.type_id = :pubStatus
        LEFT JOIN term_i18n st_i18n ON status.status_id = st_i18n.id AND st_i18n.culture = :culture
        LEFT JOIN slug ON io.id = slug.object_id
      WHERE
        $condition
        $draftsSql
      ORDER BY $orderColumn
      $limitClause;";

    $params = array(
      ':culture' => $culture,
      ':pubStatus' => QubitTerm::STATUS_TYPE_PUBLICATION_ID,
      ':id' => $id
    );

    $results = QubitPdo::fetchAll($sql, $params, array('fetchMode' => PDO::FETCH_ASSOC));

    // Get non-limited count of resulting rows
    $totalCount = QubitPdo::fetchColumn("select found_rows();");

    $data = array();

    foreach ($results as $result)
    {
      $result['baseReferenceCode'] = $baseReferenceCode;
      $result['text'] = $this->getNodeText($result);

      // Some special flags on our current selected item
      if ($result['id'] == $this->resource->id)
      {
        $result['state'] = array('opened' => true, 'selected' => true);
        $result['li_attr'] = array('selected_on_load' => true);
      }

      // Set root item's parent to hash symbol for jstree compatibility
      if ($result['parent'] == QubitInformationObject::ROOT_ID)
      {
        $result['icon'] = 'fa fa-archive';
      }

      // Add node link attributes
      $result['a_attr']['title'] = strip_tags($result['text']);
      $result['a_attr']['href'] = $this->generateUrl('slug', array('slug' => $result['slug']));

      // If not a leaf node, indicate node has children
      if ($result['rgt'] - $result['lft'] > 1)
      {
        // Set children to default of true for lazy loading
        $result['children'] = true;

        // If loading an ancestor of the resource, fetch children
        if ($result['lft'] <= $this->resource->lft && $result['rgt'] >= $this->resource->rgt)
        {
          // If we're not currently fetching children and paging options are set,
          // use paging options when fetching children
          $childOptions = array();
          if (!$children && (isset($options['skip']) || isset($options['limit'])))
          {
            $childOptions = array('skip' => $options['skip'], 'limit' => $options['limit']);
          }

          // Fetch children and note total number of children that exist (useful for paging through children)
          $childData = $this->getNodeOrChildrenNodes(
            $result['id'],
            $this->getReferenceCode($result),
            $children = true,
            $childOptions
          );

          $result['children'] = $childData['nodes'];
          $result['total'] = $childData['total'];
        }
      }

      // Not used currently
      $unset = array('lft', 'rgt', 'parent', 'lod', 'status', 'status_id', 'slug', 'source_culture', 'current_title', 'identifier');

      $data[] = array_diff_key($result, array_flip($unset));
    }

    // If not ordered in SQL, order in-memory by "text" attribute of each node
    if (!empty($options['memorySort']))
    {
      $titles = array();
      foreach ($data as $key => $node)
      {
        $titles[$key] = $node['text'];
      }

      usort($data, function($el1, $el2) { return strnatcmp($el1['text'], $el2['text']); });
    }

    return array('nodes' => $data, 'total' => $totalCount);
  }

  protected function getNodeText($result)
  {
    $text = $this->getTitle($result);

    // Prepend identifier or reference code to text
    $identifier = $this->getIdentifier($result);

    if (!empty($identifier))
    {
      $text = "$identifier - $text";
    }

    // Prepend level of description based on setting
    if (
      'yes' === sfConfig::get('app_treeview_show_level_of_description', 'yes')
      && !empty($result['lod'])
    )
    {
      $text = sprintf('[%s] %s', render_value_inline($result['lod']), $text);
    }

    // Append dates based on setting
    if ('yes' === sfConfig::get('app_treeview_show_dates', 'no'))
    {
      $dates = $this->getDates($result);

      if (!empty($dates))
      {
        $text .= ", $dates";
      }
    }

    // Prepend "(Draft)" to draft records
    if ($result['status_id'] == QubitTerm::PUBLICATION_STATUS_DRAFT_ID)
    {
      $text = sprintf('(%s) %s', render_value_inline($result['status']), $text);
    }

    return $text;
  }

  protected function getTitle($result)
  {
    // Use the current culture "title" value, if it is set
    if (!empty($result['current_title']))
    {
      return render_value_inline($result['current_title']);
    }

    // Next query the database for a source culture title
    $sql = <<<EOL
SELECT title
FROM information_object_i18n
WHERE id={$result['id']} AND source_culture={$result['source_culture']};
EOL;

    $sourceTitle = QubitPdo::fetchColumn($sql);

    // render_title will return "<em>Untitled</em>" if $sourceTitle is null
    return render_title($sourceTitle);
  }

  protected function getIdentifier($node)
  {
    // If show identifier setting is "no" return null
    if ('no' === $this->showIdentifier)
    {
      return null;
    }

    // If show identifier setting is "identifier" return the simple identifier
    if ('identifier' == $this->showIdentifier)
    {
      return render_value_inline($node['identifier']);
    }

    // Otherwise return the reference code
    return $this->getReferenceCode($node);
  }

  /**
   * Get node's reference code
   *
   * @param array $node array of treeview node values
   *
   * @return string|null reference code or null
   */
  protected function getReferenceCode($node)
  {
    // Return null if the show identifier setting is not "referenceCode" or if
    // this result has no identifier
    if (
      'referenceCode' !== $this->showIdentifier
      || empty($node['identifier']))
    {
      return null;
    }

    // If this is a top-level node return it's identifier
    if ($node['parent'] == QubitInformationObject::ROOT_ID)
    {
      return render_value_inline($node['identifier']);
    }

    // Append this result's identifier to the base reference code passed from
    // its parent
    return $node['baseReferenceCode']
      . sfConfig::get('app_separator_character', '-')
      . render_value_inline($node['identifier']);
  }

  /**
   * Get related event dates as a string
   *
   * If $node has multiple related events, the dates returned will be from the
   * first related event that has valid date data
   *
   * @param array $node array of treeview node values
   *
   * @return string related event dates
   */
  protected function getDates($node)
  {
    $dates = '';

    $sql = <<<EOL
SELECT
  event.start_date, event.end_date,
  current_i18n.date AS display_date,
  source_i18n.date AS source_date
  FROM
    event
    LEFT JOIN event_i18n current_i18n ON event.id = current_i18n.id
      AND current_i18n.culture = :culture
    LEFT JOIN event_i18n source_i18n ON event.id = source_i18n.id
      AND source_i18n.culture = event.source_culture
  WHERE
    event.object_id = :id;
EOL;

    $events = QubitPdo::fetchAll(
      $sql,
      [':culture' => $culture, ':id' => $node['id']],
      ['fetchMode' => PDO::FETCH_ASSOC]
    );

    // As in the search results from ES, get the first event with any date
    foreach ($events as $event)
    {
      if (empty($event['display_date']))
      {
        $event['display_date'] = $event['source_date'];
      }

      if (empty($event['display_date']) && empty($event['start_date']) && empty($event['end_date']))
      {
        continue;
      }

      $dates = render_value_inline(Qubit::renderDateStartEnd(
        $event['display_date'], $event['start_date'], $event['end_date']
      ));

      break;
    }

    return $dates;
  }
}
