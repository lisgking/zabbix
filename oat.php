<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Status of Web monitoring');
$page['file'] = 'httpmon.php';
$page['hist_arg'] = array('groupid', 'hostid');

define('ZBX_PAGE_DO_REFRESH', 1);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR				TYPE		OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'fullscreen' =>		array(T_ZBX_INT,	O_OPT,		P_SYS,	IN('0,1'),	null),
	'groupid' =>		array(T_ZBX_INT,	O_OPT,		P_SYS,	DB_ID,		null),
	'hostid' =>			array(T_ZBX_INT,	O_OPT,		P_SYS,	DB_ID,		null),
	// sort and sortorder
	'sort' =>			array(T_ZBX_STR, O_OPT, P_SYS, IN('"hostname","name"'),						null),
	'sortorder' =>		array(T_ZBX_STR, O_OPT, P_SYS, IN('"'.ZBX_SORT_DOWN.'","'.ZBX_SORT_UP.'"'),	null)
);
check_fields($fields);

/*
 * Permissions
 */
if (getRequest('groupid') && !API::HostGroup()->isReadable(array($_REQUEST['groupid']))) {
	access_deny();
}
if (getRequest('hostid') && !API::Host()->isReadable(array($_REQUEST['hostid']))) {
	access_deny();
}

$sortField = getRequest('sort', CProfile::get('web.'.$page['file'].'.sort', 'name'));
$sortOrder = getRequest('sortorder', CProfile::get('web.'.$page['file'].'.sortorder', ZBX_SORT_UP));

CProfile::update('web.'.$page['file'].'.sort', $sortField, PROFILE_TYPE_STR);
CProfile::update('web.'.$page['file'].'.sortorder', $sortOrder, PROFILE_TYPE_STR);

$options = array(
	'groups' => array(
		'real_hosts' => true,
		'with_httptests' => true
	),
	'hosts' => array(
		'with_monitored_items' => true,
		'with_httptests' => true
	),
	'hostid' => getRequest('hostid'),
	'groupid' => getRequest('groupid'),
);
$pageFilter = new CPageFilter($options);
$_REQUEST['groupid'] = $pageFilter->groupid;
$_REQUEST['hostid'] = $pageFilter->hostid;

$r_form = new CForm('get');
$r_form->addVar('fullscreen', $_REQUEST['fullscreen']);
$r_form->addItem(array(_('Group').SPACE,$pageFilter->getGroupsCB()));
$r_form->addItem(array(SPACE._('Host').SPACE,$pageFilter->getHostsCB()));

$httpmon_wdgt = new CWidget();
$httpmon_wdgt->addPageHeader(
	_('STATUS OF WEB MONITORING'),
	get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']))
);
$httpmon_wdgt->addHeader(_('Web scenarios'), $r_form);
$httpmon_wdgt->addHeaderRowNumber();

// TABLE
$table = new CTableInfo(_('No web scenarios found.'));
$table->SetHeader(array(
	$_REQUEST['hostid'] == 0 ? make_sorting_header(_('Host'), 'hostname', $sortField, $sortOrder) : null,
	make_sorting_header(_('Name'), 'name', $sortField, $sortOrder),
	_('Number of steps')
));
$paging = null;

$pageFilter->hostsSelected = '2';
if ($pageFilter->hostsSelected) {
    
    // 从service 层取数据
	$result = DBselect('select * from test');
	// 遍历数据,插入行数据
	while ($row = DBfetch($result)) {
	    $table->addRow(new CRow(array(
	        new CLink($row['name'], 'http://www.baidu.com'),
	        $row['name'],
	        new CSpan($row['name'], $row['name'])
	    )));
	}
	

	/* foreach ($httpTests as $httpTest) {
		if (isset($lastHttpTestData[$httpTest['httptestid']])
				&& $lastHttpTestData[$httpTest['httptestid']]['lastfailedstep'] !== null) {
			$lastData = $lastHttpTestData[$httpTest['httptestid']];

			$lastcheck = zbx_date2str(DATE_TIME_FORMAT_SECONDS, $lastData['lastcheck']);

			if ($lastData['lastfailedstep'] != 0) {
				$stepData = get_httpstep_by_no($httpTest['httptestid'], $lastData['lastfailedstep']);

				$error = ($lastData['error'] === null) ? _('Unknown error') : $lastData['error'];

				if ($stepData) {
					$status['msg'] = _s(
						'Step "%1$s" [%2$s of %3$s] failed: %4$s',
						$stepData['name'],
						$lastData['lastfailedstep'],
						$httpTest['steps'],
						$error
					);
				}
				else {
					$status['msg'] = _s('Unknown step failed: %1$s', $error);
				}

				$status['style'] = 'disabled';
			}
			else {
				$status['msg'] = _('OK');
				$status['style'] = 'enabled';
			}
		}
		// no history data exists
		else {
			$lastcheck = _('Never');
			$status['msg'] = _('Unknown');
			$status['style'] = 'unknown';
		}

		$cpsan = new CSpan($httpTest['hostname'],
			($httpTest['host']['status'] == HOST_STATUS_NOT_MONITORED) ? 'not-monitored' : ''
		);
		$table->addRow(new CRow(array(
			($_REQUEST['hostid'] > 0) ? null : $cpsan,
			new CLink($httpTest['name'], 'httpdetails.php?httptestid='.$httpTest['httptestid']),
			$httpTest['steps'],
			$lastcheck,
			new CSpan($status['msg'], $status['style'])
		)));
	} */
}
else {
	$tmp = array();
	getPagingLine($tmp, $sortOrder);
}

$iframe = new CIFrame('http://172.24.18.135:8080/openadmin/index.php');
$iframe->setWidth('100%');
$iframe->setHeight('600');

$httpmon_wdgt->addItem(array($paging, $table, $paging));
$httpmon_wdgt->show();


require_once dirname(__FILE__).'/include/page_footer.php';
