<?php


require_once dirname(__FILE__).'/include/classes/core/Z.php';

try {
	Z::getInstance()->run(ZBase::EXEC_MODE_SETUP);
}
catch (Exception $e) {
	$warningView = new CView('general.warning', array(
		'message' => array(
			'header' => 'Configuration file error', 'text' => $e->getMessage()
		)
	));
	$warningView->render();
	exit;
}

// page title
$pageTitle = '';
if (isset($ZBX_SERVER_NAME) && !zbx_empty($ZBX_SERVER_NAME)) {
	$pageTitle = $ZBX_SERVER_NAME.NAME_DELIMITER;
}
$pageTitle .= _('Installation');

$pageHeader = new CPageHeader($pageTitle);
$pageHeader->addCssInit();
$pageHeader->addCssFile('styles/themes/originalblue/main.css');
$pageHeader->addJsFile('js/jquery/jquery.js');
$pageHeader->addJsFile('js/jquery/jquery-ui.js');
$pageHeader->addJsFile('js/functions.js');
$pageHeader->addJsFile('js/test.js');

$path = 'jsLoader.php?ver='.ZABBIX_VERSION.'&amp;lang='.CWebUser::$data['lang'].'&amp;files[]=common.js&amp;files[]=main.js';
$pageHeader->addJsFile($path);

$pageHeader->display();

echo '<body class="originalblue">';

$ZBX_SETUP_WIZARD = new CSetupWizard($ZBX_CONFIG);
$ZBX_SETUP_WIZARD->show(); ?>
</body>
</html>
