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
 * Physical Object edit component.
 *
 * @package    AccesstoMemory
 * @subpackage digitalObject
 * @author     david juhasz <david@artefactual.com>
 */
class InformationObjectEditPhysicalObjectsAction extends DefaultEditPhysicalObjectsAction
{
  protected function earlyExecute()
  {
    parent::earlyExecute();

    // Check that this isn't the root
    if (property_exists($this->resource, 'parent') && !isset($this->resource->parent))
    {
      $this->forward404();
    }
  }
}
