<?php
/**
 * @package    JLSitemap - Phoca Gallery Plugin
 * @version    1.0.0
 * @author     Sergey Tolkachyov - web-tolk.ru
 * @copyright  Copyright (c) 2022 Sergey Tolkachyov. All rights reserved.
 * @license    GNU General Public License v3.0
 * @link       https://web-tolk.ru/
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Registry\Registry;
use Joomla\CMS\Uri\Uri;

class plgJLSitemapPhocagallery extends CMSPlugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var  boolean
	 *
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Method to get urls array
	 *
	 * @param   array     $urls    Urls array
	 * @param   Registry  $config  Component config
	 *
	 * @return  array Urls array with attributes
	 *
	 * @since  1.0.0
	 */
	public function onGetUrls(&$urls, $config)
	{

		$categoryExcludeStates = array(
			0  => Text::_('PLG_JLSITEMAP_CONTENT_EXCLUDE_CATEGORY_UNPUBLISH'),
			-2 => Text::_('PLG_JLSITEMAP_CONTENT_EXCLUDE_CATEGORY_TRASH'),
			2  => Text::_('PLG_JLSITEMAP_CONTENT_EXCLUDE_CATEGORY_ARCHIVE'));

		$articleExcludeStates = array(
			0  => Text::_('PLG_JLSITEMAP_CONTENT_EXCLUDE_ARTICLE_UNPUBLISH'),
			-2 => Text::_('PLG_JLSITEMAP_CONTENT_EXCLUDE_ARTICLE_TRASH'),
			2  => Text::_('PLG_JLSITEMAP_CONTENT_EXCLUDE_ARTICLE_ARCHIVE'));

		$multilanguage = $config->get('multilanguage');


		$db = Factory::getDbo();

		$query = 'SELECT *'
			. ' FROM #__phocagallery_categories AS a'
			. ' WHERE a.published = 1'
			. ' ORDER BY a.ordering';
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		$nullDate   = $db->getNullDate();
		$changefreq = $this->params->get('categories_changefreq', $config->get('changefreq', 'weekly'));
		$priority   = $this->params->get('categories_priority', $config->get('priority', '0.5'));

		$categories_images_enable = $this->params->get('categories_images_enable', 1);

		// Add categories to arrays
		$categories = array();
		$alternates = array();

		JLoader::register('PhocaGalleryLoader', JPATH_ADMINISTRATOR . '/components/com_phocagallery/libraries/loader.php');
		JLoader::register('PhocaGalleryAccess', JPATH_ADMINISTRATOR . '/components/com_phocagallery/libraries/phocagallery/access/access.php');
		JLoader::register('PhocaGalleryImageFront', JPATH_ADMINISTRATOR . '/components/com_phocagallery/libraries/phocagallery/image/imagefront.php');

		foreach ($rows as $row)
		{
			// Prepare loc attribute
			$loc = 'index.php?option=com_phocagallery&view=category&id=' . $row->id;
			if (!empty($row->language) && $row->language !== '*' && $multilanguage)
			{
				$loc .= '&lang=' . $row->language;
			}

			// Prepare exclude attribute
			$metadata = new Registry($row->metadata);
			$exclude  = array();
			if (preg_match('/noindex/', $metadata->get('robots', $config->get('siteRobots'))))
			{
				$exclude[] = array('type' => Text::_('PLG_JLSITEMAP_CONTENT_EXCLUDE_CATEGORY'),
				                   'msg'  => Text::_('PLG_JLSITEMAP_CONTENT_EXCLUDE_CATEGORY_ROBOTS'));
			}

			if (isset($categoryExcludeStates[$row->published]))
			{
				$exclude[] = array('type' => Text::_('PLG_JLSITEMAP_CONTENT_EXCLUDE_CATEGORY'),
				                   'msg'  => $categoryExcludeStates[$row->published]);
			}

			if (!in_array($row->access, $config->get('guestAccess', array())))
			{
				$exclude[] = array('type' => Text::_('PLG_JLSITEMAP_CONTENT_EXCLUDE_CATEGORY'),
				                   'msg'  => Text::_('PLG_JLSITEMAP_CONTENT_EXCLUDE_CATEGORY_ACCESS'));
			}

			// Prepare lastmod attribute
			$lastmod = (!empty($row->date) && $row->date != $nullDate) ? $row->date : false;

			// Prepare category object
			$category             = new stdClass();
			$category->type       = Text::_('PLG_JLSITEMAP_CONTENT_TYPES_CATEGORY');
			$category->title      = $row->title;
			$category->loc        = $loc;
			$category->changefreq = $changefreq;
			$category->priority   = $priority;
			$category->lastmod    = $lastmod;
			$category->exclude    = (!empty($exclude)) ? $exclude : false;
			$category->alternates = ($multilanguage && !empty($row->association)) ? $row->association : false;

			if ($categories_images_enable)
			{

				if (!empty($row->id))
				{
					$category_images = PhocaGalleryImageFront::getCategoryImages($row->id); //массив объектов
					/**
					 * @link https://developers.google.com/search/docs/advanced/sitemaps/image-sitemaps
					 * no more than 1000 images
					 */
					$images_xml = [];
					if (0 < count($category_images) && count($category_images) <= 1000)
					{// no more than 1000 images
						foreach ($category_images as $image)
						{
							$images_xml[] = JUri::root() . 'images/phocagallery/' . $image->filename;
						}
					}
					$category->images = $images_xml;

				}
			}

			// Add category to array
			$categories[] = $category;

			// Add category to alternates array
			if ($multilanguage && !empty($row->association) && empty($exclude))
			{
				if (!isset($alternates[$row->association]))
				{
					$alternates[$row->association] = array();
				}

				$alternates[$row->association][$row->language] = $loc;
			};
		}

		// Add alternates to categories
		if (!empty($alternates))
		{
			foreach ($categories as &$category)
			{
				$category->alternates = ($category->alternates) ? $alternates[$category->alternates] : false;
			}
		}

		// Add categories to urls
		$urls = array_merge($urls, $categories);


		return $urls;
	}
}
