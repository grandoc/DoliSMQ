<?php
/* Copyright (C) 2022-2023 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   	\file      view/control/control_list.php
 *		\ingroup    dolismq
 *		\brief      List page for control
 */

// Load DoliSMQ environment
if (file_exists('../dolismq.main.inc.php')) {
	require_once __DIR__ . '/../dolismq.main.inc.php';
} elseif (file_exists('../../dolismq.main.inc.php')) {
	require_once __DIR__ . '/../../dolismq.main.inc.php';
} else {
	die('Include of dolismq main fails');
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcategory.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/contact.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';

// load dolismq libraries
require_once __DIR__ . '/../../lib/dolismq_sheet.lib.php';

require_once __DIR__.'/../../class/control.class.php';
require_once __DIR__.'/../../core/boxes/dolismqwidget1.php';
require_once __DIR__ . '/../../class/sheet.class.php';
require_once __DIR__ . '/../../class/control.class.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['other', 'bills', 'projects', 'orders', 'companies', 'product', 'productbatch', 'task']);

$action      = GETPOST('action', 'aZ09') ?GETPOST('action', 'aZ09') : 'view'; // The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction  = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$show_files  = GETPOST('show_files', 'int'); // Show files area generated by bulk actions ?
$confirm     = GETPOST('confirm', 'alpha'); // Result of a confirmation
$cancel      = GETPOST('cancel', 'alpha'); // We click on a Cancel button
$toselect    = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'controllist'; // To manage different context of search
$backtopage  = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page
$optioncss   = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')
$fromtype    = GETPOST('fromtype', 'alpha'); // element type
$fromid      = GETPOST('fromid', 'int'); //element id

// Load variable for pagination
$limit     = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page      = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) { $page = 0; }     // If $page is not defined, or '' or -1 or if we click on clear filters
$offset   = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize objects
// Technical objets
$object         = new Control($db);
$box            = new dolismqwidget1($db);
$categorystatic = new Categorie($db);
$sheet          = new Sheet($db);
$extrafields    = new ExtraFields($db);
$controlstatic  = new Control($db);

// View objects
$form = new Form($db);

$hookmanager->initHooks(array('controllist')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);
//$extrafields->fetch_name_optionals_label($object->table_element_line);

if (!empty($conf->categorie->enabled)) {
	$search_category_array = GETPOST("search_category_control_list", "array");
}

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Default sort order (if not yet defined by previous GETPOST)
if (!$sortfield) { reset($object->fields); $sortfield="t.".key($object->fields); }   // Set here default search field. By default 1st field in definition. Reset is required to avoid key() to return null.
if (!$sortorder) $sortorder = "ASC";

if (!empty($fromtype)) {
	switch ($fromtype) {
		case 'project' :
			$objectLinked = new Project($db);
			break;
		case 'product' :
			$objectLinked = new Product($db);
			break;
		case 'productbatch' :
			$objectLinked = new Productlot($db);
			break;
		case 'project_task' :
			$objectLinked = new Task($db);
			break;
		case 'societe' :
			$objectLinked = new Societe($db);
			break;
		case 'contact' :
			$objectLinked = new Contact($db);
			break;
		case 'user' :
			$objectLinked = new User($db);
			break;
		case 'fk_sheet' :
			$objectLinked = new Sheet($db);
			break;
        case 'invoice' :
            $objectLinked = new Facture($db);
            break;
        case 'order' :
            $objectLinked = new Commande($db);
            break;
        case 'contract' :
            $objectLinked = new Contrat($db);
            break;
        case 'ticket' :
            $objectLinked = new Ticket($db);
            break;
	}
	$objectLinked->fetch($fromid);
	$linkedObjectsArray = array('sheet', 'user');
}

//Define custom field provide by element_element
$arrayfields['t.fk_product']    = array('type' => 'integer:Product:product/class/product.class.php', 'label' => 'Product', 'enabled' => '1', 'position' => 21, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'product.rowid', 'checked' => 1);
$arrayfields['t.fk_lot']        = array('type' => 'integer:Productlot:product/stock/class/productlot.class.php', 'label' => 'Batch', 'enabled' => '1', 'position' => 22, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'productlot.rowid', 'checked' => 1);
$arrayfields['t.fk_user']       = array('type' => 'integer:User:user/class/user.class.php', 'label' => 'User', 'enabled' => '1', 'position' => 23, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'user.rowid', 'checked' => 1);
$arrayfields['t.fk_thirdparty'] = array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'enabled' => '1', 'position' => 25, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'societe.rowid', 'checked' => 1);
$arrayfields['t.fk_contact']    = array('type' => 'integer:Contact:contact/class/contact.class.php', 'label' => 'Contact', 'enabled' => '1', 'position' => 26, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'contact.rowid', 'checked' => 1);
$arrayfields['t.fk_project']    = array('type' => 'integer:Project:projet/class/project.class.php', 'label' => 'Projet', 'enabled' => '1', 'position' => 27, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'project.rowid', 'checked' => 1);
$arrayfields['t.fk_task']       = array('type' => 'integer:Task:projet/class/task.class.php', 'label' => 'Task', 'enabled' => '1', 'position' => 28, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'task.rowid', 'checked' => 1);
$arrayfields['t.fk_invoice']    = array('type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'Invoice', 'enabled' => '1', 'position' => 29, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'facture.rowid', 'checked' => 1);
$arrayfields['t.fk_order']      = array('type' => 'integer:Commande:commande/class/commande.class.php', 'label' => 'Order', 'enabled' => '1', 'position' => 30, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'commande.rowid', 'checked' => 1);
$arrayfields['t.fk_contract']   = array('type' => 'integer:Contrat:contrat/class/contrat.class.php', 'label' => 'Contract', 'enabled' => '1', 'position' => 31, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'contrat.rowid', 'checked' => 1);
$arrayfields['t.fk_ticket']     = array('type' => 'integer:Ticket:ticket/class/ticket.class.php', 'label' => 'Ticket', 'enabled' => '1', 'position' => 32, 'notnull' => 0, 'visible' => 5, 'foreignkey' => 'ticket.rowid', 'checked' => 1);

$object->fields['fk_product']    = $arrayfields['t.fk_product'];
$object->fields['fk_lot']        = $arrayfields['t.fk_lot'];
$object->fields['fk_user']       = $arrayfields['t.fk_user'];
$object->fields['fk_thirdparty'] = $arrayfields['t.fk_thirdparty'];
$object->fields['fk_contact']    = $arrayfields['t.fk_contact'];
$object->fields['fk_project']    = $arrayfields['t.fk_project'];
$object->fields['fk_task']       = $arrayfields['t.fk_task'];
$object->fields['fk_invoice']    = $arrayfields['t.fk_invoice'];
$object->fields['fk_order']      = $arrayfields['t.fk_order'];
$object->fields['fk_contract']   = $arrayfields['t.fk_contract'];
$object->fields['fk_ticket']     = $arrayfields['t.fk_ticket'];

$elementElementFields = array(
	'fk_product'    => 'product',
	'fk_lot'        => 'productbatch',
	'fk_user'       => 'user',
	'fk_thirdparty' => 'societe',
	'fk_contact'    => 'contact',
	'fk_project'    => 'project',
	'fk_task'       => 'project_task',
	'fk_invoice'    => 'facture',
	'fk_order'      => 'commande',
	'fk_contract'   => 'contrat',
	'fk_ticket'     => 'ticket',
);

// Initialize array of search criterias
$searchAll = GETPOST('search_all', 'alphanohtml') ? GETPOST('search_all', 'alphanohtml') : GETPOST('sall', 'alphanohtml');
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha') !== '') $search[$key] = GETPOST('search_'.$key, 'alpha');
}

if(!empty($fromtype)) {
	$search_key = array_search($fromtype, $elementElementFields);
	$search[$search_key] = $fromid;
	switch ($fromtype) {
		case 'fk_sheet':
			$search['fk_sheet'] = $fromid;
			break;
		case 'user':
			$search['fk_user_controller'] = $fromid;
			break;
	}
}

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array();
foreach ($object->fields as $key => $val) {
	if ($val['searchall']) $fieldstosearchall['t.'.$key] = $val['label'];
}

// Definition of array of fields for columns
$arrayfields = array();
foreach ($object->fields as $key => $val) {
	// If $val['visible']==0, then we never show the field
	if (!empty($val['visible'])) {
		$visible = (int) dol_eval($val['visible'], 1);
		$arrayfields['t.'.$key] = array(
			'label'=>$val['label'],
			'checked'=>(($visible < 0) ? 0 : 1),
			'enabled'=>($visible != 3 && dol_eval($val['enabled'], 1)),
			'position'=>$val['position'],
			'help'=>$val['help']
		);
	}
}

$arrayfields['t.status']['checked'] = 0;

// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_array_fields.tpl.php';

$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields    = dol_sort_array($arrayfields, 'position');

$permissiontoread   = $user->rights->dolismq->control->read;
$permissiontoadd    = $user->rights->dolismq->control->write;
$permissiontodelete = $user->rights->dolismq->control->delete;

// Security check
saturne_check_access($permissiontoread, $object);

/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) { $action = 'list'; $massaction = ''; }
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') { $massaction = ''; }

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT . '/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		foreach ($object->fields as $key => $val) {
			$search[$key] = '';
			$_POST[$key] = '';
		}
		$toselect = '';
		$search_array_options = array();
		$search_category_array = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha'))
	{
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass = 'Control';
	$objectlabel = 'Control';
	$uploaddir = $conf->dolismq->dir_output;

	if (!$error && ($massaction == 'delete' || ($action == 'delete' && $confirm == 'yes')) && $permissiontodelete) {
		$db->begin();

		$objecttmp = new $objectclass($db);
		$nbok = 0;
		$TMsg = array();
		foreach ($toselect as $toselectid) {
			$result = $objecttmp->fetch($toselectid);
			if ($result > 0) {

				$objecttmp->fetchObjectLinked('','',$toselectid, 'dolismq_' . $object->element);
				$objecttmp->element = 'dolismq_' . $objecttmp->element;
				if (is_array($objecttmp->linkedObjects) && !empty($objecttmp->linkedObjects)) {
					foreach($objecttmp->linkedObjects as $linkedObjectType => $linkedObjectArray) {
						foreach($linkedObjectArray as $linkedObject) {
							if (method_exists($objecttmp, 'isErasable') && $objecttmp->isErasable() <= 0) {
								$objecttmp->deleteObjectLinked($linkedObject->id, $linkedObjectType);
							}
						}
					}
				}

				$result = $objecttmp->delete($user);

				if (empty($result)) { // if delete returns 0, there is at least one object linked
					$TMsg = array_merge($objecttmp->errors, $TMsg);
				} elseif ($result < 0) { // if delete returns is < 0, there is an error, we break and rollback later
					setEventMessages($objecttmp->error, $objecttmp->errors, 'errors');
					$error++;
					break;
				} else {
					$nbok++;
				}
			} else {
				setEventMessages($objecttmp->error, $objecttmp->errors, 'errors');
				$error++;
				break;
			}
		}

		if (empty($error)) {
			// Message for elements well deleted
			if ($nbok > 1) {
				setEventMessages($langs->trans("RecordsDeleted", $nbok), null, 'mesgs');
			} elseif ($nbok > 0) {
				setEventMessages($langs->trans("RecordDeleted", $nbok), null, 'mesgs');
			}

			// Message for elements which can't be deleted
			if (!empty($TMsg)) {
				sort($TMsg);
				setEventMessages('', array_unique($TMsg), 'warnings');
			}

			$db->commit();
		} else {
			$db->rollback();
		}

		//var_dump($listofobjectthirdparties);exit;
	}

//	include DOL_DOCUMENT_ROOT . '/core/actions_massactions.inc.php';
}

/*
 * View
 */

$now      = dol_now();
$help_url = '';
$title    = $langs->trans("ControlList");

saturne_header(0,'', $title, $help_url);
if (!empty($fromtype)) {
	print saturne_get_fiche_head($objectLinked, 'control', $langs->trans("Control"));

	$linkback = '<a href="'.DOL_URL_ROOT.'/'.$fromtype.'/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

	saturne_banner_tab($objectLinked, 'ref', '', 0);
}

if ($fromid) {
	print '<div class="underbanner clearboth"></div>';
	print '<div class="fichehalfleft">';
	print '<br>';
	$controls = $controlstatic->fetchAll();

	if (is_array($controls) && !empty($controls)) {
		foreach ($controls as $control) {
			if (!empty($control->linkedObjectsIds)) {
				if (array_key_exists($fromtype, $control->linkedObjectsIds)) {
					$test = array_values($control->linkedObjectsIds[$fromtype]);
					if ($test[0] == $fromid) {
						$sheet->fetch($control->fk_sheet);
						$categories = $categorystatic->getListForItem($control->id, $control->element);
						if (is_array($categories) && !empty($categories)) {
							foreach ($categories as $category) {
								$nbBox[$category['label']] = 1;
							}
						}
					}
				}
			}
		}

		if (!empty($categories)) {
			$box->loadBox();
			if (is_array($nbBox) || is_object($nbBox)) {
				for ($i = 0; $i < count($nbBox); $i++) {
					$box->showBox($i,$i);
				}
			}
		}
	}
	print '</div>';
}

$newcardbutton = dolGetButtonTitle($langs->trans('NewControl'), '', 'fa fa-plus-circle', dol_buildpath('/dolismq/view/control/control_card.php', 1).'?action=create', '', $permissiontoadd);

include_once '../../core/tpl/dolismq_control_list.tpl.php';

// End of page
llxFooter();
$db->close();
