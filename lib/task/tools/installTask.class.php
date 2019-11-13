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
 * AtoM Installation Task
 *
 * @package    symfony
 * @subpackage task
 * @author     Mike Cantelon <mike@artefactual.com>
 */
class installTask extends arBaseTask
{
  protected
    $actor = null;

  protected function configure()
  {
    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_OPTIONAL, 'The application name', true),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'cli'),
      new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The connection name', 'propel'),
      new sfCommandOption('demo', null, sfCommandOption::PARAMETER_NONE, 'Use default demo values, do not ask for confirmation'),
      new sfCommandOption('database-host', null, sfCommandOption::PARAMETER_OPTIONAL, 'Database host'),
      new sfCommandOption('database-name', null, sfCommandOption::PARAMETER_OPTIONAL, 'Database name'),
      new sfCommandOption('database-username', null, sfCommandOption::PARAMETER_OPTIONAL, 'Database username'),
      new sfCommandOption('database-password', null, sfCommandOption::PARAMETER_OPTIONAL, 'Database password'),
      new sfCommandOption('search-host', null, sfCommandOption::PARAMETER_OPTIONAL, 'Search host'),
      new sfCommandOption('search-port', null, sfCommandOption::PARAMETER_OPTIONAL, 'Search port'),
      new sfCommandOption('search-index', null, sfCommandOption::PARAMETER_OPTIONAL, 'Search index'),

    ));

    $this->namespace = 'tools';
    $this->name = 'install';
    $this->briefDescription = 'Install AtoM.';
    $this->detailedDescription = <<<EOF
Install AtoM.
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    parent::execute($arguments, $options);

    /*
    if (!$options['demo'] && !function_exists('readline'))
    {
      $needed = array('title', 'description', 'url', 'email', 'username', 'password');
      if (!array_key_exists($needed, $options))
      {
        throw new Exception('At least one of the following command line '.
          'options is missing: title, description, url, email, username '.
          'and/or password.');
      }
    }
    */

    # TODO: maybe just have it so you can provide and installation profile
    if ($options['demo'])
    {
      $options['database-host'] = 'localhost';
      $options['database-name'] = 'atom';
      $options['database-username'] = 'atom';
      $options['database-password'] = 'ATOMPASSWORD';

      $options['search-host'] = 'localhost';
      $options['search-port'] = '9200';
      $options['search-index'] = 'atom';
    }

    # TODO: check dependencies

    if (!$options['databaseHost'])
    {
      $options['databaseHost'] = readline("Database host [localhost]: ");
      $options['databaseHost'] = (!empty($options['databaseHost'])) ? $options['databaseHost'] : 'localhost';
    }

    if (!$options['databaseName'])
    {
      $options['databaseName'] = readline("Database name [atom]: ");
      $options['databaseName'] = (!empty($options['databaseName'])) ? $options['databaseName'] : 'atom';
    }

    if (!$options['databaseUsername'])
    {
      $options['databaseUsername'] = readline("Database username [atom]: ");
      $options['databaseUsername'] = (!empty($options['databaseUsername'])) ? $options['databaseUsername'] : 'atom';
    }

    if (!$options['databasePassword'])
    {
      $options['databasePassword'] = readline("Database password: ");
    }

    $databaseOptions = array(
      'databaseHost'     => $options['database-host'],
      'databaseName'     => $options['database-name'],
      'databaseUsername' => $options['database-username'],
      'databasePassword' => $options['database-password'],
    );

    if ($errors = sfInstall::configureDatabase($databaseOptions))
    {
      print_r($errors);
      exit(1);
    }

    sfInstall::addSymlinks();

    if (!$options['searchHost'])
    {
      $options['searchHost'] = readline("Search host [localhost]: ");
      $options['searchHost'] = (!empty($options['searchHost'])) ? $options['searchHost'] : 'localhost';
    }

    if (!$options['searchPort'])
    {
      $options['searchPort'] = readline("Search port [9200]: ");
      $options['searchPort'] = (!empty($options['searchPort'])) ? $options['searchPort'] : '9200';
    }

    if (!$options['searchIndex'])
    {
      $options['searchIndex'] = readline("Search index [atom]: ");
      $options['searchIndex'] = (!empty($options['searchIndex'])) ? $options['searchIndex'] : '9200';
    }

    $searchOptions = array(
      'searchHost'  => $options['searchHost'],
      'searchPort'  => $options['searchPort'],
      'searchIndex' => $options['searchIndex']
    );

    if ($errors = sfInstall::configureSearch($searchOptions))
    {
      print_r($errors);
      exit(1);
    }

    $this->log('Initializing data...');

    sfInstall::insertSql();
    sfInstall::loadData();

    $this->log('Populating search index...');

    sfInstall::populateSearchIndex();

    $this->log('Installation complete!');
  }
}
