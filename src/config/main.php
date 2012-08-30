<?php

$common = require_once(BLOCKS_APP_PATH.'config/common.php');

Yii::setPathOfAlias('app', BLOCKS_APP_PATH);
Yii::setPathOfAlias('config', BLOCKS_CONFIG_PATH);
Yii::setPathOfAlias('plugins', BLOCKS_PLUGINS_PATH);
Yii::setPathOfAlias('runtime', BLOCKS_RUNTIME_PATH);
Yii::setPathOfAlias('templates', BLOCKS_TEMPLATES_PATH);

return CMap::mergeArray(
	$common,

	array(
		'basePath'    => BLOCKS_APP_PATH,
		'runtimePath' => BLOCKS_RUNTIME_PATH,
		'name'        => 'Blocks',

		// autoloading model and component classes
		'import' => array(
			'application.business.lib.*',
			'application.business.lib.PhpMailer.*',
			'application.business.lib.Requests.*',
			'application.business.lib.Requests.Auth.*',
			'application.business.lib.Requests.Response.*',
			'application.business.lib.Requests.Transport.*',
		),

		// application components
		'components' => array(
			'accounts' => array(
				'class' => 'Blocks\AccountsService',
			),

			// services
			'assets' => array(
				'class' => 'Blocks\AssetsService',
			),

			'blocks' => array(
				'class' => 'Blocks\BlocksService',
			),

			'content' => array(
				'class' => 'Blocks\ContentService',
			),

			'dashboard' => array(
				'class' => 'Blocks\DashboardService',
			),

			'email' => array(
				'class' => 'Blocks\EmailService',
			),

			'et' => array(
				'class' => 'Blocks\EtService',
			),

			'installer' => array(
				'class' => 'Blocks\InstallService',
			),

			'migrations' => array(
				'class' => 'Blocks\MigrationsService',
			),

			'path' => array(
				'class' => 'Blocks\PathService',
			),

			'plugins' => array(
				'class' => 'Blocks\PluginsService',
			),

			'security' => array(
				'class' => 'Blocks\SecurityService',
			),

			'settings' => array(
				'class' => 'Blocks\SettingsService',
			),

			'updates' => array(
				'class' => 'Blocks\UpdatesService',
			),
			// end services

			'file' => array(
				'class' => 'Blocks\File',
			),

			'messages' => array(
				'class' => 'Blocks\PhpMessageSource',
			),

			'request' => array(
				'class' => 'Blocks\HttpRequest',
				'enableCookieValidation'      => true,
			),

			'viewRenderer' => array(
				'class' => 'Blocks\TemplateProcessor',
			),

			'statePersister' => array(
				'class' => 'Blocks\StatePersister'
			),

			'urlManager' => array(
				'class' => 'Blocks\UrlManager',
				'cpRoutes' => array(
					array('update\/(?P<handle>[^\/]*)',                        'update'),
					/* BLOCKS ONLY */
					array('myaccount',                                         'users/_edit/account'),
					/* end BLOCKS ONLY */
					/* BLOCKSPRO ONLY */
					array('users\/new',                                        'users/_edit/account'),
					array('users\/(?P<userId>\d+)',                            'users/_edit/account'),
					array('users\/(?P<userId>\d+)\/profile',                   'users/_edit/profile'),
					array('users\/(?P<userId>\d+)\/admin',                     'users/_edit/admin'),
					array('users\/(?P<userId>\d+)\/info',                      'users/_edit/info'),
					/* end BLOCKSPRO ONLY */
					array('content\/(?P<entryId>\d+)',                         'content/_entry'),
					array('content\/(?P<entryId>\d+)\/draft(?P<draftNum>\d+)', 'content/_entry'),
					array('plugins\/(?P<pluginClass>[A-Za-z]\w*)',             'plugins/_settings'),
					/* BLOCKSPRO ONLY */
					array('settings\/sections\/new',                           'settings/sections/_edit'),
					array('settings\/sections\/(?P<sectionId>\d+)',            'settings/sections/_edit'),
					/* end BLOCKSPRO ONLY */
				),
			),

			'assetManager' => array(
				'basePath' => dirname(__FILE__).'/../assets',
				'baseUrl' => '../../blocks/app/assets',
			),

			'errorHandler' => array(
				'class' => 'Blocks\ErrorHandler'
			),

			'fileCache' => array(
				'class' => 'CFileCache',
			),

			'log' => array(
				'class' => 'Blocks\LogRouter',
				'routes' => array(
					array(
						'class'  => 'Blocks\FileLogRoute',
					),
					array(
						'class'         => 'Blocks\WebLogRoute',
						'filter'        => 'CLogFilter',
						'showInFireBug' => true,
					),
					array(
						'class'         => 'Blocks\ProfileLogRoute',
						'showInFireBug' => true,
					),
				),
			),

			'routes' => array(
				'class' => 'Blocks\RoutesService'
			),

			'session' => array(
				'autoStart'     => true,
				'cookieMode'    => 'only',
				'class'         => 'Blocks\HttpSession',
				'sessionName'   => 'BlocksSessionId',
			),

			'user' => array(
				'class'             => 'Blocks\UserSessionService',
				'allowAutoLogin'    => true,
				'loginUrl'          => array('/login'),
				'autoRenewCookie'   => true,
			),
		),

		'params' => array(
			'blocksConfig'         => $blocksConfig,
			'requiredPhpVersion'   => '5.3.0',
			'requiredMysqlVersion' => ''
		),
	)
);
