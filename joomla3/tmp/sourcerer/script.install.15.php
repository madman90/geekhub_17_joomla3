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

define('ROOT', dirname(__FILE__));

jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

// Load language for messaging
$lang = JFactory::getLanguage();
if ($lang->getTag() != 'en-GB') {
	// Loads English language file as fallback (for undefined stuff in other language file)
	$lang->load('com_nonumberinstaller', ROOT, 'en-GB');
}
$lang->load('com_nonumberinstaller', ROOT, null, 1);

JFactory::getApplication()->enqueueMessage(JText::sprintf('NNI_NOT_COMPATIBLE_OLD', round(JVERSION, 1)), 'error');
cleanupInstall();
uninstallInstaller();

/* FUNCTIONS */

/**
 * Cleanup install files/folders
 */
function cleanupInstall()
{
	$installer = JInstaller::getInstance();
	$source = str_replace('\\', '/', $installer->getPath('source'));
	$config = JFactory::getConfig();
	$tmp = dirname(str_replace('\\', '/', $config->getValue('config.tmp_path') . '/x'));

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

function uninstallInstaller($alias = 'nonumberinstaller')
{
	$app = JFactory::getApplication();
	// Create database object
	$db = JFactory::getDBO();

	$query = 'SELECT `id` FROM `#__components`'
		. ' WHERE `option` = ' . $db->quote('com_' . $alias)
		. ' AND `parent` = 0'
		. ' LIMIT 1';
	$db->setQuery($query);
	$id = (int) $db->loadResult();
	if ($id > 1) {
		$installer = JInstaller::getInstance();
		$installer->uninstall('component', $id);
	}
	$query = 'ALTER TABLE `#__components` AUTO_INCREMENT = 1';
	$db->setQuery($query);
	$db->query();

	// Delete language files
	$lang_folder = JPATH_ADMINISTRATOR . '/language';
	$languages = JFolder::folders($lang_folder);
	foreach ($languages as $lang) {
		$file = $lang_folder . '/' . $lang . '/' . $lang . '.com_' . $alias . '.ini';
		if (JFile::exists($file)) {
			JFile::delete($file);
		}
	}

	// Delete old language files
	$files = JFolder::files(JPATH_SITE . '/language', 'com_nonumberinstaller.ini');
	foreach ($files as $file) {
		JFile::delete(JPATH_SITE . '/language/' . $file);
	}

	// Redirect with message
	$app->redirect('index.php?option=com_installer');
}

/**
 * Copies all files from install folder
 */
function copy_from_folder($folder, $force = 0)
{
	if (!is_dir($folder)) {
		return 0;
	}

	// Copy files
	$folders = JFolder::folders($folder);

	$success = 1;

	foreach ($folders as $subfolder) {
		if (!folder_copy($folder . '/' . $subfolder, JPATH_SITE . '/' . $subfolder, $force)) {
			$success = 0;
		}
	}

	return $success;
}

/**
 * Copy a folder
 */
function folder_copy($src, $dest, $force = 0)
{
	$app = JFactory::getApplication();

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
	if (!JFolder::exists($dest) && !folder_create($dest)) {
		$folder = str_replace(JPATH_ROOT, '', $dest);
		$app->enqueueMessage(JText::_('NNI_FAILED_TO_CREATE_DIRECTORY') . ': ' . $folder, 'error error_folders');
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
			if (!folder_copy($folder_src, $folder_dest, $force)) {
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
					$app->enqueueMessage(JText::_('NNI_ERROR_SAVING_FILE') . ': ' . $file_path, 'error error_files');
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
					$app->enqueueMessage(JText::_('NNI_ERROR_SAVING_FILE') . ': ' . $file_path, 'error error_files');
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
function folder_create($path = '', $mode = 0755)
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
