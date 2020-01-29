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

class amaMarcXmlParser extends QubitSaxParser
{
  protected $termCounter;
  protected $obsoleteTermCounter;
  protected $termData;
  protected $currentTagAttr;
  protected $obsoleteTerms;

  public function __construct($encoding = 'UTF-8')
  {
    parent::__construct($encoding);

    $this->termCounter = 0;
  }

  /*
   * Tags functions
   */

  protected function mx_collectionTagInit()
  {
    $this->obsoleteTerms = array();

    print 'Starting collection parsing';
  }

  protected function mx_recordTagInit()
  {
    // Initiate term data
    $this->termData = array(
    );
  }

  protected function mx_leaderTag()
  {
    if (!empty($this->data()))
    {
      $statusCode = substr(trim($this->data()), 5, 1);

      $obsoleteCodes = array('d', 'o', 's', 'x');

      if (in_array($statusCode, $obsoleteCodes))
      {
        $this->termData['directive'] = 'This authority record has been deleted, the heading is now covered by:';
      }
    }
  }

  protected function mx_datafieldTagInit()
  {
    // Get current tag, needed to determine termData
    // field in the subfield tag function bellow
    $this->currentTagAttr = $this->attr('tag');
  }

  protected function mx_subfieldTag()
  {
    // A tag attribute from the datafield is required
    if (!isset($this->currentTagAttr))
    {
      return;
    }

    // Data is only needed from the following code attributes
    $codeAttr = $this->attr('code');
    if (!isset($codeAttr) || !in_array($codeAttr, array('a', 'i')))
    {
      return;
    }

    // Do not import empty subfield elements
    $data = trim($this->data());
    if (strlen($data) === 0)
    {
      return;
    }

    // Add data to termData based on the datafield tag attribute
    switch ($this->currentTagAttr)
    {
      case '016':
        if ($codeAttr === 'a')
        {
          $fastId = str_replace('fst', '', $data) + 0;
          $this->termData['url'] = 'http://id.worldcat.org/fast/'. $fastId;
        }

        break;

      /*
      case '024':
        if ($codeAttr === 'a')
        {
          $this->termData['url'] = trim($data);
        }

        break;
      */

      /*
      case '682':
        if ($codeAttr === 'i' && $this->termData['directive'] != 'This authority record has been deleted, the heading is now covered by:')
        {
          $this->termData['directive'] = trim($data);
        }

        break;
      */
    }
  }

  protected function mx_recordTag()
  {
    if ($this->termData['directive'] == 'This authority record has been deleted, the heading is now covered by:')
    {
      $this->obsoleteTerms[($this->termData['url'])] = true;

      $this->obsoleteTermCounter++;
      print_r($this->termData);
    }

    $this->termCounter++;
  }

  protected function mx_collectionTag()
  {
    print 'Collection parsing finished ('. $this->termCounter .' terms, '. $this->obsoleteTermCounter .' - '. count($this->obsoleteTerms) .' unique - obsolete terms)';
  }

  public function finish()
  {
  }

  public function getUrls()
  {
    return array_keys($this->obsoleteTerms);
  }
}
