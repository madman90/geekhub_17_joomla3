<?php
/**
 * Extension Install File
 * Does the stuff for the specific extensions
 *
 * @package         Sourcerer
 * @version         4.0.2
 *
 * @author          Peter van Westen <peter@nonumber.nl>
 * @link            http://www.nonumber.nl
 * @copyright       Copyright © 2012 NoNumber All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

$name = 'Sourcerer';
$alias = 'sourcerer';
$ext = $name . ' (system plugin & editor button plugin)';

// SYSTEM PLUGIN
$states[] = installExtension($states, $alias, 'System - ' . $name, 'plugin');

// EDITOR BUTTON PLUGIN
$states[] = installExtension($states, $alias, 'Editor Button - ' . $name, 'plugin', array('folder' => 'editors-xtd'));

