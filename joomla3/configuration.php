<?php
class JConfig {
	public $offline = '0';
	public $offline_message = 'Сайт закрыт на техническое обслуживание.<br /> Пожалуйста, зайдите позже.';
	public $display_offline_message = '1';
	public $offline_image = '';
	public $sitename = 'GeekHub';
	public $editor = 'tinymce';
	public $captcha = '0';
	public $list_limit = '20';
	public $access = '1';
	public $debug = '0';
	public $debug_lang = '0';
	public $dbtype = 'mysql';
	public $host = 'localhost';
	public $user = 'root';
	public $password = 'root';
	public $db = 'geekhub';
	public $dbprefix = 'vtxfg_';
	public $live_site = '';
	public $secret = 'xYoUbhWG1KWppGTo';
	public $gzip = '0';
	public $error_reporting = 'default';
	public $helpurl = 'http://help.joomla.org/proxy/index.php?option=com_help&amp;keyref=Help{major}{minor}:{keyref}';
	public $ftp_host = '';
	public $ftp_port = '';
	public $ftp_user = '';
	public $ftp_pass = '';
	public $ftp_root = '';
	public $ftp_enable = '';
	public $offset = 'UTC';
	public $mailer = 'mail';
	public $mailfrom = 'madman-90@mail.ru';
	public $fromname = 'GeekHub';
	public $sendmail = '/usr/sbin/sendmail';
	public $smtpauth = '0';
	public $smtpuser = '';
	public $smtppass = '';
	public $smtphost = 'localhost';
	public $smtpsecure = 'none';
	public $smtpport = '25';
	public $caching = '0';
	public $cache_handler = 'file';
	public $cachetime = '15';
	public $MetaDesc = '';
	public $MetaKeys = '';
	public $MetaTitle = '1';
	public $MetaAuthor = '1';
	public $MetaVersion = '0';
	public $robots = '';
	public $sef = '1';
	public $sef_rewrite = '0';
	public $sef_suffix = '0';
	public $unicodeslugs = '0';
	public $feed_limit = '10';
	public $log_path = '/var/www/joomla3/logs';
	public $tmp_path = '/var/www/joomla3/tmp';
	public $lifetime = '15';
	public $session_handler = 'database';
}