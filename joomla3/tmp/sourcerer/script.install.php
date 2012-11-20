<?php
/**
 * Installer File
 * Performs an install / update of NoNumber extensions
 *
 * @package         NoNumber Installer
 * @version         12.11.7
 *
 * @author          Peter van Westen <peter@nonumber.nl>
 * @link            http://www.nonumber.nl
 * @copyright       Copyright © 2012 NoNumber All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

define('JV', (version_compare(JVERSION, '3', 'l')) ? 'j2' : 'j3');
define('ROOT', dirname(__FILE__));

jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

class com_NoNumberInstallerInstallerScript
{
	protected $_ext = 'nonumberinstaller';

	public function preflight($adapter)
	{
		// Install the Installer languages
		self::installLanguages(ROOT . '/installer/language', 1, 0);

		// Load language for messaging
		if (JFactory::getLanguage()->getTag() != 'en-GB') {
			// Loads English language file as fallback (for undefined stuff in other language file)
			JFactory::getLanguage()->load('com_' . $this->_ext, ROOT, 'en-GB');
		}
		JFactory::getLanguage()->load('com_' . $this->_ext, ROOT, null, 1);

		if (version_compare(PHP_VERSION, '5.3', 'l')) {
			self::cleanup(JText::sprintf('NNI_NOT_COMPATIBLE_PHP', PHP_VERSION, '5.3'));
		}
		if (version_compare(JVERSION, '2.5', 'l')) {
			self::cleanup(JText::sprintf('NNI_NOT_COMPATIBLE', round(JVERSION, 1)));
		}

		$install_file = ROOT . '/extensions.php';
		if (!JFile::exists($install_file) || !is_readable($install_file)) {
			self::cleanup(JText::sprintf('NNI_CANNOT_READ_THE_REQUIRED_INSTALLATION_FILE', $install_file));
		} else if (!JFolder::exists(ROOT . '/extensions/' . JV)) {
			self::cleanup(JText::sprintf('NNI_NOT_COMPATIBLE', round(JVERSION, 1)));
		}

		$states = array();
		$ids = array();
		$has_installed = 0;
		$has_updated = 0;
		$has_error = 0;

		$ext = 'NNI_THE_EXTENSION'; // default value. Will be overruled in extensions.php
		require_once $install_file;

		if (is_array($states)) {
			foreach ($states as $state) {
				if (is_array($state)) {
					$ids[] = $state['1'];
					$state = $state['0'];
				}
				if ($state === 2) {
					$has_updated = 1;
				} else if ($state === 1) {
					$has_installed = 1;
				} else {
					$has_installed = $has_updated = 0;
					if ($state === -1) {
						$has_error = 1;
					}
					break;
				}
			}
		}

		if (!$has_installed && !$has_updated) {
			$error = '';
			if (!$has_error) {
				$error = JText::_('NNI_SOMETHING_HAS_GONE_WRONG_DURING_INSTALLATION_OF_THE_DATABASE_RECORDS');
			}
			self::cleanup($error);
		}

		if (!self::installFiles(ROOT . '/extensions')) {
			self::cleanup(JText::_('NNI_COULD_NOT_COPY_ALL_FILES'));
		}

		if (!empty($ids)) {
			$installer = JInstaller::getInstance();
			foreach ($ids as $id) {
				$installer->refreshManifestCache((int) $id);
			}
		}

		$txt_installed = ($has_installed) ? JText::_('NNI_INSTALLED') : '';
		$txt_installed .= ($has_installed && $has_updated) ? ' / ' : '';
		$txt_installed .= ($has_updated) ? JText::_('NNI_UPDATED') : '';
		//JFactory::getApplication()->set('_messageQueue', '');
		JFactory::getApplication()->enqueueMessage(sprintf(JText::_('NNI_THE_EXTENSION_HAS_BEEN_INSTALLED_SUCCESSFULLY'), JText::_($ext), $txt_installed), 'message');
		JFactory::getApplication()->enqueueMessage(JText::_('NNI_PLEASE_CLEAR_YOUR_BROWSERS_CACHE'), 'notice');

		self::installFramework();

		self::cleanup();
	}

	/**
	 * Copies language files to the language folders
	 */
	private function installLanguages($folder, $force = 1, $all = 1, $break = 1)
	{
		if (JFolder::exists($folder . '/admin')) {
			$path = JPATH_ADMINISTRATOR . '/language';
			if (!self::installLanguagesByPath($folder . '/admin', $path, $force, $all, $break) && $break) {
				return 0;
			}
		}
		if (JFolder::exists($folder . '/site')) {
			$path = JPATH_SITE . '/language';
			if (!self::installLanguagesByPath($folder . '/site', $path, $force, $all, $break) && $break) {
				return 0;
			}
		}
		return 1;
	}

	/**
	 * Removes language files from the language admin folders by filter
	 */
	private function uninstallLanguages($filter)
	{
		$languages = JFolder::folders(JPATH_ADMINISTRATOR . '/language');
		foreach ($languages as $lang) {
			$files = JFolder::files(JPATH_ADMINISTRATOR . '/language/' . $lang, $filter);
			foreach ($files as $file) {
				JFile::delete(JPATH_ADMINISTRATOR . '/language/' . $lang . '/' . $file);
			}
		}
	}

	/**
	 * Copies all files from install folder
	 */
	private function copy_from_folder($folder, $force = 0)
	{
		if (!is_dir($folder)) {
			return 0;
		}

		// Copy files
		$folders = JFolder::folders($folder);

		$success = 1;

		foreach ($folders as $subfolder) {
			$dest = JPATH_SITE . '/' . $subfolder;
			$dest = str_replace(JPATH_SITE . '/plugins', JPATH_PLUGINS, $dest);
			$dest = str_replace(JPATH_SITE . '/administrator', JPATH_ADMINISTRATOR, $dest);
			if (!self::folder_copy($folder . '/' . $subfolder, $dest, $force)) {
				$success = 0;
			}
		}

		return $success;
	}

	/**
	 * Copy a folder
	 */
	private function folder_copy($src, $dest, $force = 0)
	{
		// Initialize variables
		jimport('joomla.client.helper');
		$ftpOptions = JClientHelper::getCredentials('ftp');

		// Eliminate trailing directory separators, if any
		$src = rtrim(str_replace('\\', '/', $src), '/');
		$dest = rtrim(str_replace('\\', '/', $dest), '/');

		if (!JFolder::exists($src)) {
			return 0;
		}

		$success = 1;

		// Make sure the destination exists
		if (!JFolder::exists($dest) && !self::folder_create($dest)) {
			$folder = str_replace(JPATH_ROOT, '', $dest);
			JFactory::getApplication()->enqueueMessage(JText::_('NNI_FAILED_TO_CREATE_DIRECTORY') . ': ' . $folder, 'error error_folders');
			$success = 0;
		}

		if (!($dh = @opendir($src))) {
			return 0;
		}

		$folders = array();
		$files = array();
		while (($file = readdir($dh)) !== false) {
			if ($file != '.' && $file != '..') {
				$file_src = $src . '/' . $file;
				switch (filetype($file_src)) {
					case 'dir':
						$folders[] = $file;
						break;
					case 'file':
						$files[] = $file;
						break;
				}
			}
		}
		sort($folders);
		sort($files);

		$curr_folder = array_pop(explode('/', $src));
		// Walk through the directory recursing into folders
		foreach ($folders as $folder) {
			$folder_src = $src . '/' . $folder;
			$folder_dest = $dest . '/' . $folder;
			if (!($curr_folder == 'language' && !JFolder::exists($folder_dest))) {
				if (!self::folder_copy($folder_src, $folder_dest, $force)) {
					$success = 0;
				}
			}
		}

		if ($ftpOptions['enabled'] == 1) {
			// Connect the FTP client
			jimport('joomla.client.ftp');
			$ftp = JFTP::getInstance(
				$ftpOptions['host'], $ftpOptions['port'], null,
				$ftpOptions['user'], $ftpOptions['pass']
			);

			// Walk through the directory copying files
			foreach ($files as $file) {
				$file_src = $src . '/' . $file;
				$file_dest = $dest . '/' . $file;
				// Translate path for the FTP account
				$file_dest = JPath::clean(str_replace(str_replace('\\', '/', JPATH_ROOT), $ftpOptions['root'], $file_dest), '/');
				if ($force || !JFile::exists($file_dest)) {
					if (!$ftp->store($file_src, $file_dest)) {
						$file_path = str_replace($ftpOptions['root'], '', $file_dest);
						JFactory::getApplication()->enqueueMessage(JText::_('NNI_ERROR_SAVING_FILE') . ': ' . $file_path, 'error error_files');
						$success = 0;
					}
				}
			}
		} else {
			foreach ($files as $file) {
				$file_src = $src . '/' . $file;
				$file_dest = $dest . '/' . $file;
				if ($force || !JFile::exists($file_dest)) {
					if (!@copy($file_src, $file_dest)) {
						$file_path = str_replace(JPATH_ROOT, '', $file_dest);
						JFactory::getApplication()->enqueueMessage(JText::_('NNI_ERROR_SAVING_FILE') . ': ' . $file_path, 'error error_files');
						$success = 0;
					}
				}
			}
		}

		return $success;
	}

	/**
	 * Create a folder
	 */
	private function folder_create($path = '', $mode = 0755)
	{
		// Initialize variables
		jimport('joomla.client.helper');
		$ftpOptions = JClientHelper::getCredentials('ftp');

		// Check to make sure the path valid and clean
		$path = JPath::clean($path);

		// Check if dir already exists
		if (JFolder::exists($path)) {
			return true;
		}

		// Check for safe mode
		if ($ftpOptions['enabled'] == 1) {
			// Connect the FTP client
			jimport('joomla.client.ftp');
			$ftp = JFTP::getInstance(
				$ftpOptions['host'], $ftpOptions['port'], null,
				$ftpOptions['user'], $ftpOptions['pass']
			);

			// Translate path to FTP path
			$path = JPath::clean(str_replace(JPATH_ROOT, $ftpOptions['root'], $path), '/');
			$ret = $ftp->mkdir($path);
			$ftp->chmod($path, $mode);
		} else {
			// We need to get and explode the open_basedir paths
			$obd = ini_get('open_basedir');

			// If open_basedir is set we need to get the open_basedir that the path is in
			if ($obd != null) {
				if (JPATH_ISWIN) {
					$obdSeparator = ";";
				} else {
					$obdSeparator = ":";
				}
				// Create the array of open_basedir paths
				$obdArray = explode($obdSeparator, $obd);
				$inBaseDir = false;
				// Iterate through open_basedir paths looking for a match
				foreach ($obdArray as $test) {
					$test = JPath::clean($test);
					if (strpos($path, $test) === 0) {
						$inBaseDir = true;
						break;
					}
				}
				if ($inBaseDir == false) {
					// Return false for JFolder::create because the path to be created is not in open_basedir
					JError::raiseWarning(
						'SOME_ERROR_CODE',
						'JFolder::create: ' . JText::_('NNI_PATH_NOT_IN_OPEN_BASEDIR_PATHS')
					);
					return false;
				}
			}

			// First set umask
			$origmask = @umask(0);

			// Create the path
			if (!$ret = @mkdir($path, $mode)) {
				@umask($origmask);
				return false;
			}

			// Reset umask
			@umask($origmask);
		}

		return $ret;
	}

	private function getXMLVersion($file = '', $alias = '', $type = '', $folder = 'system')
	{
		if (!$file) {
			if (!$alias || !$type) {
				return 0;
			}
			switch ($type) {
				case 'component':
					if (JFile::exists(JPATH_ADMINISTRATOR . '/components/com_' . $alias . '/' . $alias . '.xml')) {
						$file = JPATH_ADMINISTRATOR . '/components/com_' . $alias . '/' . $alias . '.xml';
					} else if (JFile::exists(JPATH_SITE . '/components/com_' . $alias . '/' . $alias . '.xml')) {
						$file = JPATH_SITE . '/components/com_' . $alias . '/' . $alias . '.xml';
					} else if (JFile::exists(JPATH_ADMINISTRATOR . '/components/com_' . $alias . '/com_' . $alias . '.xml')) {
						$file = JPATH_ADMINISTRATOR . '/components/com_' . $alias . '/com_' . $alias . '.xml';
					} else if (JFile::exists(JPATH_SITE . '/components/com_' . $alias . '/com_' . $alias . '.xml')) {
						$file = JPATH_SITE . '/components/com_' . $alias . '/com_' . $alias . '.xml';
					}
					break;
				case 'plugin':
					if (JFile::exists(JPATH_PLUGINS . '/' . $folder . '/' . $alias . '/' . $alias . '.xml')) {
						$file = JPATH_PLUGINS . '/' . $folder . '/' . $alias . '/' . $alias . '.xml';
					} else if (JFile::exists(JPATH_PLUGINS . '/' . $folder . '/' . $alias . '.xml')) {
						$file = JPATH_PLUGINS . '/' . $folder . '/' . $alias . '.xml';
					}
					break;
				case 'module':
					if (JFile::exists(JPATH_ADMINISTRATOR . '/modules/mod_' . $alias . '/' . $alias . '.xml')) {
						$file = JPATH_ADMINISTRATOR . '/modules/mod_' . $alias . '/' . $alias . '.xml';
					} else if (JFile::exists(JPATH_SITE . '/modules/mod_' . $alias . '/' . $alias . '.xml')) {
						$file = JPATH_SITE . '/modules/mod_' . $alias . '/' . $alias . '.xml';
					} else if (JFile::exists(JPATH_ADMINISTRATOR . '/modules/mod_' . $alias . '/mod_' . $alias . '.xml')) {
						$file = JPATH_ADMINISTRATOR . '/modules/mod_' . $alias . '/mod_' . $alias . '.xml';
					} else if (JFile::exists(JPATH_SITE . '/modules/mod_' . $alias . '/mod_' . $alias . '.xml')) {
						$file = JPATH_SITE . '/modules/mod_' . $alias . '/mod_' . $alias . '.xml';
					}
					break;
			}
		}

		if (!$file || !JFile::exists($file)) {
			return 0;
		}

		$xml = JApplicationHelper::parseXMLInstallFile($file);

		if (!$xml || !isset($xml['version'])) {
			return 0;
		}
		return $xml['version'];
	}

	private function cleanup($error = '')
	{
		if ($error) {
			JFactory::getApplication()->enqueueMessage($error, 'error');
		}
		self::cleanupInstall();
		self::uninstallInstaller();
	}

	/**
	 * Cleanup install files/folders
	 */
	private function cleanupInstall()
	{
		$installer = JInstaller::getInstance();
		$source = str_replace('\\', '/', $installer->getPath('source'));
		$tmp = dirname(str_replace('\\', '/', JFactory::getConfig()->get('tmp_path') . '/x'));

		if (strpos($source, $tmp) === false || $source == $tmp) {
			return;
		}

		$package_folder = dirname($source);
		if ($package_folder == $tmp) {
			$package_folder = $source;
		}

		$package_file = '';
		switch (JFactory::getApplication()->input->getString('installtype')) {
			case 'url':
				$package_file = JFactory::getApplication()->input->getString('install_url');
				$package_file = str_replace(dirname($package_file), '', $package_file);
				break;
			case 'upload':
			default:
				if (isset($_FILES) && isset($_FILES['install_package']) && isset($_FILES['install_package']['name'])) {
					$package_file = $_FILES['install_package']['name'];
				}
				break;
		}
		if (!$package_file && $package_folder != $source) {
			$package_file = str_replace($package_folder . '/', '', $source) . '.zip';
		}

		$package_file = $tmp . '/' . $package_file;

		JInstallerHelper::cleanupInstall($package_file, $package_folder);
	}

	/**
	 * Copies all files from install folder
	 */
	private function installFiles($folder)
	{
		if (JFolder::exists($folder . '/all')) {
			if (!self::copy_from_folder($folder . '/all', 1)) {
				return 0;
			}
		}
		if (JFolder::exists($folder . '/' . JV)) {
			if (!self::copy_from_folder($folder . '/' . JV, 1)) {
				return 0;
			}
		}
		if (JFolder::exists($folder . '/' . JV . '_optional')) {
			if (!self::copy_from_folder($folder . '/' . JV . '_optional', 0)) {
				return 0;
			}
		}
		if (JFolder::exists($folder . '/language')) {
			self::installLanguages($folder . '/language');
		}
		return 1;
	}

	/**
	 * Copies language files to the specified path
	 */
	private function installLanguagesByPath($folder, $path, $force = 1, $all = 1, $break = 1)
	{
		if ($all) {
			$languages = JFolder::folders($path);
		} else {
			$lang = JFactory::getLanguage();
			$languages = array($lang->getTag());
		}
		$languages[] = 'en-GB'; // force to include the English files
		$languages = array_unique($languages);

		if (JFolder::exists($path . '/en-GB')) {
			self::folder_create($path . '/en-GB');
		}

		foreach ($languages as $lang) {
			if (!JFolder::exists($folder . '/' . $lang)) {
				continue;
			}
			$files = JFolder::files($folder . '/' . $lang);
			foreach ($files as $file) {
				$src = $folder . '/' . $lang . '/' . $file;
				$dest = $path . '/' . $lang . '/' . $file;
				if (!(strpos($file, '.menu.ini') === false)) {
					if (JFile::exists($dest)) {
						JFile::delete($dest);
					}
					continue;
				}
				if ($force || JFile::exists($src)) {
					if (!JFile::copy($src, $dest) && $break) {
						return 0;
					}
				}
			}
		}
		return 1;
	}

	public function installExtension($states, $alias, $name, $type = 'component', $extra = array(), $reinstall = 0)
	{
		foreach ($states as $state) {
			if (is_array($state)) {
				$ids[] = $state['1'];
				$state = $state['0'];
			}
			if ($state < 1) {
				return -1;
			}
		}

		// Create database object
		$db = JFactory::getDBO();

		// set main vars
		$element = $alias;
		$folder = ($type == 'plugin') ? (isset($extra['folder']) ? $extra['folder'] : 'system') : '';
		unset($extra['folder']);

		// set main database where clauses
		$where = array();
		$where[] = $db->qn('type') . ' = ' . $db->q($type);
		switch ($type) {
			case 'component':
				$element = 'com_' . $element;
				break;
			case 'plugin':
				$where[] = $db->qn('folder') . ' = ' . $db->q($folder);
				break;
			case 'module':
				$element = 'mod_' . $element;
				break;
		}
		$where[] = $db->qn('element') . ' = ' . $db->q($element);
		$where = implode(' AND ', $where);

		// get ordering
		$ordering = '';
		switch ($type) {
			case 'plugin':
				$query = $db->getQuery(true);
				$query->select('ordering');
				$query->from('#__extensions');
				$query->where($where);
				$db->setQuery($query);
				$ordering = $db->loadResult();
				break;
			case 'module':
				$query = $db->getQuery(true);
				$query->select('m.ordering');
				$query->from('#__modules AS m');
				$query->where('m.module = ' . $db->q($element) . ' OR m.module = ' . $db->q('mod_' . $element));
				$db->setQuery($query);
				$ordering = $db->loadResult();
				break;
		}

		// get installed state
		$installed = 0;
		if ($reinstall) {
			// remove extension(s) from database
			$query = $db->getQuery(true);
			$query->delete();
			$query->from('#__extensions');
			$query->where($where);
			$db->setQuery($query);
			$db->execute();
			if (in_array($db->name, array('mysql', 'mysqli'))) {
				// reset auto increment
				$query = 'ALTER TABLE `#__extensions` AUTO_INCREMENT = 1';
				$db->setQuery($query);
				$db->execute();
			}
			if ($type == 'module') {
				// remove module(s) from database
				$query = $db->getQuery(true);
				$query->delete();
				$query->from('#__modules');
				$query->where('module = ' . $db->q($element) . ' OR module = ' . $db->q('mod_' . $element));
				$db->setQuery($query);
				$db->execute();
				if (in_array($db->name, array('mysql', 'mysqli'))) {
					// reset auto increment
					$query = 'ALTER TABLE `#__modules` AUTO_INCREMENT = 1';
					$db->setQuery($query);
					$db->execute();
				}
			}
		} else {
			// get installed state
			$query = $db->getQuery(true);
			$query->select('extension_id');
			$query->from('#__extensions');
			$query->where($where);
			$db->setQuery($query);
			$installed = (int) $db->loadResult();
		}

		// check if FREE version can be installed
		if ($installed) {
			$version = self::getXMLVersion('', $alias, $type, $folder);
			if ($version) {
				$n = preg_replace('#^.*? - #', '', $name);
				if (!(strpos($version, 'PRO') === false)) {
					// return if current version is PRO
					$url = 'http://www.nonumber.nl/go-pro?ext=' . $alias . '" target="_blank';
					JFactory::getApplication()->enqueueMessage(JText::_('NNI_ERROR_PRO_TO_FREE') . '<br /><br />' . html_entity_decode(JText::sprintf('NNI_ERROR_UNINSTALL_FIRST', $url, $n)), 'error');
					return -1;
				} else if (strpos($version, 'FREE') === false && $alias != 'nonumbermanager') {
					// return if current version is not FREE (=before switch)
					$url = 'http://www.nonumber.nl/extensions/' . $alias . '" target="_blank';
					JFactory::getApplication()->enqueueMessage(JText::_('NNI_ERROR_BEFORE_SWITCH') . '<br /><br />' . html_entity_decode(JText::sprintf('NNI_ERROR_UNINSTALL_FIRST', $url, $n)), 'error');
					return -1;
				}
			}
		}

		// execute custom beforeInstall function
		if (function_exists('beforeInstall')) {
			beforeInstall($db);
		}

		$id = $installed;

		// if not installed yet, create database entries
		if (!$installed) {
			if ($type == 'module') {
				// create module database object
				$row = JTable::getInstance('module');
				$row->title = $name;
				$row->module = $element;
				$row->client_id = 1;
				$row->published = 1;
				$row->position = 'status';
				$row->showtitle = 1;
				$row->language = '*';
				foreach ($extra as $key => $val) {
					if (property_exists($row, $key)) {
						$row->$key = $val;
					}
				}
				if ($ordering) {
					$row->ordering = $ordering;
				} else {
					$row->ordering = $row->getNextOrder("position='" . $row->position . "' AND client_id = " . $row->client_id);
				}
				// save module to database
				if (!$row->store()) {
					JFactory::getApplication()->enqueueMessage($row->getError(), 'error');
					return 0;
				}

				// clean up possible garbage first
				$query = $db->getQuery(true);
				$query->delete();
				$query->from('#__modules_menu');
				$query->where('moduleid = ' . (int) $row->id);
				$db->setQuery($query);
				$db->execute();

				// create a menu entry for the module
				$query = $db->getQuery(true);
				$query->insert('#__modules_menu');
				$query->values((int) $row->id . ', 0');
				$db->setQuery($query);
				$db->execute();
			}

			// create extension database object
			$row = JTable::getInstance('extension');
			$row->name = strtolower($alias);
			$row->element = $alias;
			$row->type = $type;
			$row->enabled = 1;
			$row->client_id = 0;
			$row->access = 1;
			switch ($type) {
				case 'component':
					$row->name = strtolower('com_' . $row->name);
					$row->element = 'com_' . $row->element;
					$row->access = 0;
					$row->client_id = 1;
					break;
				case 'plugin':
					$row->name = strtolower('plg_' . $folder . '_' . $row->name);
					$row->folder = $folder;
					if ($ordering) {
						$row->ordering = $ordering;
					}
					break;
				case 'module':
					$row->name = strtolower('mod_' . $row->name);
					$row->element = 'mod_' . $row->element;
					$row->client_id = 1;
					break;
			}
			foreach ($extra as $key => $val) {
				if (property_exists($row, $key)) {
					$row->$key = $val;
				}
			}

			// save extension to database
			if (!$row->store()) {
				JFactory::getApplication()->enqueueMessage($row->getError(), 'error');
				return 0;
			}
			$id = (int) $row->extension_id;
		}

		// if no extension id is found, return 0 (=not installed)
		if (!$id) {
			return 0;
		}

		// remove manifest cache
		$query = $db->getQuery(true);
		$query->update('#__extensions AS e');
		$query->set('e.manifest_cache = ' . $db->q(''));
		$query->where('e.extension_id = ' . (int) $id);
		$db->setQuery($query);
		$db->execute();

		// add menus for components
		if ($type == 'component') {
			// delete old menu entries
			$query = $db->getQuery(true);
			$query->delete();
			$query->from('#__menu');
			$query->where('link = ' . $db->q('index.php?option=com_' . $alias));
			$query->where('client_id = 1');
			$db->setQuery($query);
			$db->execute();

			// find menu details in xml file
			$xml = 0;
			$file = ROOT . '/extensions/' . JV . '/administrator/components/com_' . $alias . '/' . $alias . '.xml';

			if (JFile::exists($file)) {
				$xml = JFactory::getXML($file);
			}

			if ($xml && isset($xml->administration) && isset($xml->administration->menu)) {
				$menuElement = $xml->administration->menu;

				if ($menuElement) {
					// create menu database object
					$data = array();
					$data['menutype'] = 'menu';
					$data['client_id'] = 1;
					$data['title'] = (string) $menuElement;
					$data['alias'] = $alias;
					$data['link'] = 'index.php?option=' . 'com_' . $alias;
					$data['type'] = 'component';
					$data['published'] = 1;
					$data['parent_id'] = 1;
					$data['component_id'] = $id;
					$attribs = $menuElement->attributes();
					$data['img'] = ((string) $attribs->img) ? (string) $attribs->img : 'class:component';
					$data['home'] = 0;
					$data['language'] = '*';
					$table = JTable::getInstance('menu');

					// save menu to database
					try {
						$table->setLocation(1, 'last-child');
					} catch (InvalidArgumentException $e) {
						return 0;
					}
					if (!$table->bind($data) || !$table->check() || !$table->store()) {
						JFactory::getApplication()->enqueueMessage($table->getError(), 'error');
						return 0;
					}
				}
			}
		}

		// execute custom afterInstall function
		if (function_exists('afterInstall_' . JV)) {
			afterInstall_j2($db);
		} else if (function_exists('afterInstall')) {
			afterInstall($db);
		}

		if ($alias != 'nnframework') {
			$url = 'http://download.nonumber.nl/updates.php?e=' . $alias;
			self::addUpdateSite($id, $name, 'extension', $url . '&');
		}

		// return 2 for already installed (=update) and 1 for not yet installed (=install)
		return array((($installed) ? 2 : 1), $id);
	}

	private function addUpdateSite($id, $name, $type, $location, $enabled = true)
	{
		$name = preg_replace('#^.*? - #', '', $name);

		$db = JFactory::getDBO();

		$query = $db->getQuery(true);
		$query->delete();
		$query->from('#__update_sites');
		$query->where('(name = ' . $db->quote($name) . ' AND location != ' . $db->quote($location) . ')');
		$query->where('(name != ' . $db->quote($name) . ' AND location = ' . $db->quote($location) . ')', 'OR');
		$db->setQuery($query);
		$db->execute();

		$query->clear();
		$query->delete();
		$query->from('#__update_sites');
		$query->where('name != ' . $db->quote($name))->where('location = ' . $db->quote($location));
		$db->setQuery($query);
		$db->execute();

		$query->clear();
		$query->select('update_site_id')->from('#__update_sites')->where('location = ' . $db->quote($location));
		$db->setQuery($query);
		$update_site_id = (int) $db->loadResult();

		if (!$update_site_id) {
			$query->clear();
			$query->insert('#__update_sites');
			$query->columns(array($db->quoteName('name'), $db->quoteName('type'), $db->quoteName('location'), $db->quoteName('enabled')));
			$query->values($db->quote($name) . ', ' . $db->quote($type) . ', ' . $db->quote($location) . ', ' . (int) $enabled);
			$db->setQuery($query);
			$db->execute();
			$update_site_id = $db->insertid();
		}

		$query->clear();
		$query->delete();
		$query->from('#__updates');
		$query->where('update_site_id = ' . $update_site_id);
		$db->setQuery($query);
		$db->execute();

		$query->clear();
		$query->delete();
		$query->from('#__update_sites_extensions');
		$query->where('extension_id = ' . $id);
		$db->setQuery($query);
		$db->execute();

		$query->clear();
		$query->insert('#__update_sites_extensions');
		$query->columns(array($db->quoteName('update_site_id'), $db->quoteName('extension_id')));
		$query->values($update_site_id . ', ' . $id);
		$db->setQuery($query);
		$db->execute();
	}

	private function installFramework()
	{
		$framework_folder = ROOT . '/framework/framework';
		$xml_name = 'plugins/system/nnframework/nnframework.xml';
		$xml_file = $framework_folder . '/' . JV . '/' . $xml_name;
		if (!JFile::exists($xml_file)) {
			return;
		}

		$do_install = 1;

		$new_version = self::getXMLVersion($xml_file);
		if ($new_version) {
			$do_install = 1;
			$current_version = self::getXMLVersion('', 'nnframework', 'plugin');
			if ($current_version) {
				$do_install = version_compare($current_version, $new_version, '<=') ? 1 : 0;
			}
		}

		$success = 1;
		if ($do_install) {
			if (!self::installFiles($framework_folder)) {
				JFactory::getApplication()->enqueueMessage('Could not install the NoNumber Framework extension', 'error');
				JFactory::getApplication()->enqueueMessage('Could not copy all files', 'error');
				$success = 0;
			}
			if ($success) {
				$elements_folder = ROOT . '/framework/elements';
				if (JFolder::exists(JPATH_PLUGINS . '/system/nonumberelements') && JFolder::exists($elements_folder)) {
					self::uninstallLanguages('nonumberelements');
					if (self::installFiles($elements_folder)) {
						self::installExtension(array(), 'nonumberelements', 'System - NoNumber Elements', 'plugin', array('published' => '0'), 1);
					}
				}
			}
		}

		if ($success) {
			self::installExtension(array(), 'nnframework', 'System - NoNumber Framework', 'plugin', array(), 1);
		}
	}

	private function uninstallInstaller()
	{
		// Create database object
		$db = JFactory::getDBO();

		$query = $db->getQuery(true);
		$query->delete();
		$query->from('#__menu');
		$query->where('title = ' . $db->q('com_' . $this->_ext));
		$db->setQuery($query);
		$db->execute();
		if (in_array($db->name, array('mysql', 'mysqli'))) {
			$query = 'ALTER TABLE `#__menu` AUTO_INCREMENT = 1';
			$db->setQuery($query);
			$db->execute();
		}

		// Delete language files
		$lang_folder = JPATH_ADMINISTRATOR . '/language';
		$languages = JFolder::folders($lang_folder);
		foreach ($languages as $lang) {
			$file = $lang_folder . '/' . $lang . '/' . $lang . '.com_' . $this->_ext . '.ini';
			if (JFile::exists($file)) {
				JFile::delete($file);
			}
		}

		// Delete old language files
		$files = JFolder::files(JPATH_SITE . '/language', 'com_' . $this->_ext . '.ini');
		foreach ($files as $file) {
			JFile::delete(JPATH_SITE . '/language/' . $file);
		}

		if (JFile::exists(JPATH_ADMINISTRATOR . '/components/' . 'com_' . $this->_ext)) {
			JFolder::delete(JPATH_ADMINISTRATOR . '/components/' . 'com_' . $this->_ext);
		}

		// Redirect with message
		JFactory::getApplication()->redirect('index.php?option=com_installer');
	}
}

function installExtension($states, $alias, $name, $type = 'component', $extra = array(), $reinstall = 0)
{
	return com_nonumberInstallerInstallerScript::installExtension($states, $alias, $name, $type, $extra, $reinstall);
}
