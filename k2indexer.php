<?php defined('_JEXEC') or die;

/**
 * File       indexed_tags_mass_update.php
 * Created    1/6/14 12:59 PM
 * Author     Matt Thomas | matt@betweenbrain.com | http://betweenbrain.com
 * Support    https://github.com/betweenbrain/
 * Copyright  Copyright (C) 2014 betweenbrain llc. All Rights Reserved.
 * License    GNU GPL v3 or later
 */

jimport('joomla.error.log');

class plgSystemK2indexer extends JPlugin
{

	function plgSystemK2indexer(&$subject, $params)
	{
		parent::__construct($subject, $params);

		$this->app = JFactory::getApplication();
		$this->db  = JFactory::getDBO();
		$this->log = JLog::getInstance();
	}

	function onAfterRoute()
	{
		if ($this->app->isAdmin() && ($type = JRequest::getVar('indexk2')))
		{
			$ids = $this->getItemIds(JRequest::getVar('category'));

			foreach ($ids as $id)
			{
				echo '<pre>Indexing ' . $type . ' item ' . $id . '</pre><br/>';
				$function = 'get' . $type;
				$items    = $this->$function($id);
				$this->setExtraFieldsSearchData($id, $items);
				$this->setpluginsData($id, $items, $type);
			}
		}
	}

	/**
	 * Adds data to the extra_fields_search column of a K2 item
	 *
	 * @param $id
	 * @param $data
	 */
	private function setExtraFieldsSearchData($id, $data)
	{
		$data  = implode(' ', $data);
		$query = 'UPDATE ' . $this->db->nameQuote('#__k2_items') . '
				SET ' . $this->db->nameQuote('extra_fields_search') . ' = CONCAT(
					' . $this->db->nameQuote('extra_fields_search') . ',' . $this->db->Quote($data) . '
				)
				WHERE id = ' . $this->db->Quote($id) . '';
		$this->db->setQuery($query);
		$this->db->query();
		$this->checkDbError();
	}

	private function getItemIds($categoryId)
	{
		$query = 'SELECT id
				FROM ' . $this->db->nameQuote('#__k2_items') . '
				WHERE catid = ' . $this->db->Quote($categoryId) . '';

		$this->db->setQuery($query);
		$tags = $this->db->loadResultArray();
		$this->checkDbError();

		return $tags;
	}

	/**
	 * function to fetch an item's categories
	 *
	 * @param $id
	 *
	 * @return mixed
	 */
	private function getCategories($id)
	{

		$query = 'SELECT catid
			FROM ' . $this->db->nameQuote('#__k2_items') . '
			WHERE Id = ' . $this->db->Quote($id);

		$this->db->setQuery($query);
		$catIds[] = $this->db->loadResult();
		$this->checkDbError();

		$addCatsPlugin = JPluginHelper::isEnabled('k2', 'k2additonalcategories');

		if ($addCatsPlugin)
		{

			$query = 'SELECT catid
				FROM ' . $this->db->nameQuote('#__k2_additional_categories') . '
				WHERE itemID = ' . $this->db->Quote($id);

			$this->db->setQuery($query);
			$addCats = $this->db->loadResultArray();
			$this->checkDbError();

			foreach ($addCats as $addCat)
			{
				$catIds[] = $addCat;
			}
		}

		$query = 'SELECT name
			FROM ' . $this->db->nameQuote('#__k2_categories') . '
			WHERE Id IN (' . implode(',', $catIds) . ')
			AND published = 1';

		$this->db->setQuery($query);
		$categories = $this->db->loadResultArray();
		$this->checkDbError();

		return $categories;
	}

	/**
	 * function to fetch a K2 item's tags
	 *
	 * @param $id
	 *
	 * @return mixed
	 */
	private function getTags($id)
	{
		$query = 'SELECT tag.name
				FROM ' . $this->db->nameQuote('#__k2_tags') . '  as tag
				LEFT JOIN ' . $this->db->nameQuote('#__k2_tags_xref') . '
				AS xref ON xref.tagID = tag.id
				WHERE xref.itemID = ' . $this->db->Quote($id) . '
				AND tag.published = 1';

		$this->db->setQuery($query);
		$tags = $this->db->loadResultArray();
		$this->checkDbError();

		return $tags;
	}

	/**
	 * Sets the plugins data for the specified K2 item
	 *
	 * @param $id
	 * @param $data
	 * @param $type
	 */
	private function setpluginsData($id, $data, $type)
	{

		$pluginsData  = $this->getpluginsData($id);
		$pluginsArray = parse_ini_string($pluginsData, false, INI_SCANNER_RAW);
		if ($data)
		{
			$pluginsArray[$type] = implode('|', $data);
		}
		else
		{
			unset($pluginsArray[$type]);
		}
		$pluginData = null;
		foreach ($pluginsArray as $key => $value)
		{
			$pluginData .= "$key=" . $value . "\n";
		}

		$query = 'UPDATE ' . $this->db->nameQuote('#__k2_items') .
			' SET ' . $this->db->nameQuote('plugins') . '=\'' . $pluginData . '\'' .
			' WHERE id = ' . $this->db->Quote($id) . '';

		$this->db->setQuery($query);
		$this->db->query();
		$this->checkDbError();
	}

	/**
	 * Gets the plugins data for the specified K2 item
	 *
	 * @param $id
	 *
	 * @return mixed
	 */
	private function getpluginsData($id)
	{
		$query = 'SELECT ' . $this->db->nameQuote('plugins') .
			' FROM ' . $this->db->nameQuote('#__k2_items') .
			' WHERE id = ' . $this->db->Quote($id) . '';

		$this->db->setQuery($query);
		$pluginsData = $this->db->loadResult();
		$this->checkDbError();

		return $pluginsData;
	}

	/**
	 * Checks for any database errors after running a query
	 *
	 * @throws Exception
	 */
	private function checkDbError()
	{
		if ($error = $this->db->getErrorMsg())
		{
			$this->log->addEntry(array('LEVEL' => '1', 'STATUS' => 'Database Error:', 'COMMENT' => $error));
			throw new Exception($error);
		}
	}
}
