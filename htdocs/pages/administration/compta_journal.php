<?php

// Impossible to access the file itself
if (!defined('PAGE_LOADED_USING_INDEX')) {
    trigger_error("Direct access forbidden.", E_USER_ERROR);
    exit;
}

$action = verifierAction([
    'lister',
    'debit',
    'credit',
    'ajouter',
    'modifier',
    'supprimer',
    'importer',
    'ventiler',
    'modifier_colonne',
    'export',
]);

$smarty->assign('action', $action);

require_once dirname(__FILE__).'/../../../sources/Afup/AFUP_Compta.php';
$compta = new AFUP_Compta($bdd);


if (isset($_GET['id_periode']) && $_GET['id_periode']) {
	$id_periode=$_GET['id_periode'];
} else {
	$id_periode="";
}

$id_periode = $compta->obtenirPeriodeEnCours($id_periode);
$smarty->assign('id_periode', $id_periode);

$listPeriode = $compta->obtenirListPeriode();
$smarty->assign('listPeriode', $listPeriode );


	$periode_debut=$listPeriode[$id_periode-1]['date_debut'];
	$periode_fin=$listPeriode[$id_periode-1]['date_fin'];

// Function added to Smarty in order to add the paybox link if possible
function paybox_link($description)
{
    $matches = array();
    if (preg_match('`CB\s+AFUP\s+([0-9]{2})([0-9]{2})([0-9]{2})-CB\s+AFUP`', $description, $matches)) {
        $date = $matches[1] . "/" . $matches[2] . "/" . (2000 + (int) $matches[3]);
        $url  = sprintf('https://admin.paybox.com/cgi/CBDCum.cgi?lg=FR&amp;SelDate=%1$s&amp;SelDateAu=%1$s', $date);
        return sprintf('<a href="%2$s" class="js-paybox-link">%1$s</a>', $description, $url);
    }
    return $description;
}
$smarty->register_modifier('paybox_link', 'paybox_link');

if ($action == 'lister') {

    // Accounting lines for the selected period
    $journal = $compta->obtenirJournal('', $periode_debut, $periode_fin);
    $smarty->assign('journal', $journal);

    // Categories
    $categories    = $compta->obtenirListCategories();
    $categories[0] = "-- À déterminer --";
    $smarty->assign('categories', $categories);

    // Events
    $events    = $compta->obtenirListEvenements();
    $events[0] = "-- À déterminer --";
    $smarty->assign('events', $events);

    // Payment methods
    $paymentMethods    = $compta->obtenirListReglements();
    $paymentMethods[0] = "-- À déterminer --";
    $smarty->assign('payment_methods', $paymentMethods);

}
elseif ($action == 'debit') {
	$journal = $compta->obtenirJournal(1,$periode_debut,$periode_fin);
	$smarty->assign('journal', $journal);
}
elseif ($action == 'credit') {
	$journal = $compta->obtenirJournal(2,$periode_debut,$periode_fin);
	$smarty->assign('journal', $journal);

} elseif ($action == 'ajouter' || $action == 'modifier') {

  	$formulaire = &instancierFormulaire();

   if ($action == 'modifier')
   {
        $champsRecup = $compta->obtenir($_GET['id']);

        $champs['idcompte']          = $champsRecup['idcompte'];
        $champs['date_saisie']          = $champsRecup['date_ecriture'];
        $champs['idoperation']          = $champsRecup['idoperation'];
        $champs['idcategorie']          = $champsRecup['idcategorie'];
        $champs['nom_frs']          = $champsRecup['nom_frs'];
        $champs['montant']          = $champsRecup['montant'];
        $champs['description']          = $champsRecup['description'];
        $champs['numero']          = $champsRecup['numero'];
        $champs['idmode_regl']          = $champsRecup['idmode_regl'];
        $champs['date_reglement']          = $champsRecup['date_regl'];
        $champs['obs_regl']          = $champsRecup['obs_regl'];
        $champs['idevenement']          = $champsRecup['idevenement'];


		//$formulaire->setDefaults($champsRecup);
		$formulaire->addElement('hidden', 'id', $_GET['id']);
   } else {
       $champs['idcompte'] = 1;
       $champs['date_saisie'] = date('Y-m-d');
       $champs['date_reglement'] = date('Y-m-d');
   }
   $formulaire->setDefaults($champs);

// facture associé à un évènement
   $formulaire->addElement('header'  , ''                         , 'Sélectionner un Journal');
   $formulaire->addElement('select'  , 'idoperation', 'Type d\'opération', $compta->obtenirListOperations());
   $formulaire->addElement('select'  , 'idcompte'   , 'Compte', $compta->obtenirListComptes());
   $formulaire->addElement('select'  , 'idevenement', 'Evenement', $compta->obtenirListEvenements());

//detail facture
   $formulaire->addElement('header'  , ''                         , 'Détail Facture');

//$mois=10;
   $formulaire->addElement('date'    , 'date_saisie'     , 'Date saisie', array('language' => 'fr',
                                                                                'format'   => 'd F Y',
  																				'minYear' => date('Y')-5,
  																				'maxYear' => date('Y')+1));

  $formulaire->addElement('select'  , 'idcategorie', 'Type de compte', $compta->obtenirListCategories());
  $formulaire->addElement('text', 'nom_frs', 'Nom fournisseurs' , array('size' => 30, 'maxlength' => 40));
   	$formulaire->addElement('text', 'numero', 'Numero facture' , array('size' => 30, 'maxlength' => 40));
   	$formulaire->addElement('textarea', 'description', 'Description', array('cols' => 42, 'rows' => 5));
	$formulaire->addElement('text', 'montant', 'Montant' , array('size' => 30, 'maxlength' => 40));

//reglement
   $formulaire->addElement('header'  , ''                         , 'Réglement');
   $formulaire->addElement('select'  , 'idmode_regl', 'Réglement', $compta->obtenirListReglements());
   $formulaire->addElement('date'    , 'date_reglement'     , 'Date', array('language' => 'fr',
                                                                            'format'   => 'd F Y',
   																			'minYear' => date('Y')-5,
   																			'maxYear' => date('Y')+1));
   $formulaire->addElement('text', 'obs_regl', 'Info reglement' , array('size' => 30, 'maxlength' => 40));


// boutons
    $formulaire->addElement('header'  , 'boutons'                  , '');
    $formulaire->addElement('submit'  , 'soumettre'                , ucfirst($action));

	// 2012-02-18 A. Gendre
	$passer = null;
	if($action != 'ajouter'){
		$res = $compta->obtenirSuivantADeterminer($_GET['id']);
		if(is_array($res)){
			$passer = $res['id'];
			$formulaire->addElement('submit', 'soumettrepasser'   , 'Soumettre & passer');
			$formulaire->addElement('submit', 'passer'   , 'Passer');
		}
	}

	// ajoute des regles
	$formulaire->addRule('idoperation'   , 'Type d\'opération manquant'    , 'required');
	$formulaire->addRule('idcompte'      , 'Compte manquant'    , 'required');
	$formulaire->addRule('idoperation'   , 'Type d\'opération manquant'    , 'nonzero');
	$formulaire->addRule('idevenement'    , 'Evenement manquant'   , 'required');
	$formulaire->addRule('idevenement'    , 'Evenement manquant'   , 'nonzero');
	$formulaire->addRule('idcategorie'    , 'Type de compte manquant'     , 'required');
	$formulaire->addRule('idcategorie'    , 'Type de compte manquant'     , 'nonzero');
	$formulaire->addRule('montant'       , 'Montant manquant'      , 'required');


	// 2012-02-18 A. Gendre
	if (isset($_POST['passer']) && isset($passer)) {
		 afficherMessage('L\'écriture n\'a pas été ' . (($action == 'ajouter') ? 'ajoutée' : 'modifiée'), 'index.php?page=compta_journal&action=modifier&id=' . $passer);
		 return;
	}

    if ($formulaire->validate()) {
		$valeur = $formulaire->exportValues();

$date_ecriture= $valeur['date_saisie']['Y']."-".$valeur['date_saisie']['F']."-".$valeur['date_saisie']['d'] ;
$date_regl=$valeur['date_reglement']['Y']."-".$valeur['date_reglement']['F']."-".$valeur['date_reglement']['d'] ;

    	if ($action == 'ajouter') {
   			$ok = $compta->ajouter(
            						$valeur['idoperation'],
            						$valeur['idcompte'],
            						$valeur['idcategorie'],
            						$date_ecriture,
            						$valeur['nom_frs'],
            						$valeur['montant'],
            						$valeur['description'],
									$valeur['numero'],
									$valeur['idmode_regl'],
									$date_regl,
									$valeur['obs_regl'],
									$valeur['idevenement']
            						);
        } else {
   			$ok = $compta->modifier(
            						$valeur['id'],
            						$valeur['idoperation'],
            						$valeur['idcompte'],
            						$valeur['idcategorie'],
            						$date_ecriture,
            						$valeur['nom_frs'],
            						$valeur['montant'],
            						$valeur['description'],
									$valeur['numero'],
									$valeur['idmode_regl'],
									$date_regl,
									$valeur['obs_regl'],
									$valeur['idevenement']
            						);
        }

        if ($ok) {
            if ($action == 'ajouter') {
                AFUP_Logs::log('Ajout une écriture ' . $formulaire->exportValue('titre'));
            } else {
                AFUP_Logs::log('Modification une écriture ' . $formulaire->exportValue('titre') . ' (' . $_GET['id'] . ')');
            }
			// 2012-02-18 A. Gendre
			if (isset($_POST['soumettrepasser']) && isset($passer)) {
				$urlredirect = 'index.php?page=compta_journal&action=modifier&id=' . $passer;
			} else {
				$urlredirect = 'index.php?page=compta_journal&action=lister#L' . $valeur['id'];
			}
			afficherMessage('L\'écriture a été ' . (($action == 'ajouter') ? 'ajoutée' : 'modifiée'), $urlredirect);
        } else {
            $smarty->assign('erreur', 'Une erreur est survenue lors de ' . (($action == 'ajouter') ? "l'ajout" : 'la modification') . ' de l\'écriture');
        }
    }


    $smarty->assign('formulaire', genererFormulaire($formulaire));
}

/*
 * This action allows the admin to export the full period in a CSV file.
 * This is really useful when you need to filter by columns using Excel.
 */
elseif ($action === 'export') {
    $journal = $compta->obtenirJournal('', $periode_debut, $periode_fin);

    // Pointer to output
    $fp = fopen('php://output', 'w');

    // CSV
    $csvDelimiter = ';';
    $csvEnclosure = '"';
    $csvFilename  = sprintf(
        'AFUP_%s_journal_from-%s_to-%s.csv',
        date('Y-M-d'),
        $periode_debut,
        $periode_fin
    );

    // headers
    header('Content-Type: text/csv');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"$csvFilename\"");

    // First line
    $columns = [
        'Date',
        'Compte',
        'Evénement',
        'Catégorie',
        'Description',
        'Débit',
        'Crédit',
        'Règlement',
        'Commentaire',
    ];
    fputcsv($fp, $columns, $csvDelimiter, $csvEnclosure);

    // Set the current local and get variables to use in number_format
    $l = setlocale(LC_ALL, 'fr_FR.utf8');
    $locale = localeconv();

    foreach ($journal as $line) {
        $total = number_format($line['montant'], 2, $locale['decimal_point'], $locale['thousands_sep']);
        fputcsv(
            $fp,
            [
                $line['date_ecriture'],
                $line['nom_compte'],
                $line['evenement'],
                $line['categorie'],
                $line['description'],
                $line['idoperation'] == 1 ? "-$total" : '',
                $line['idoperation'] != 1 ? $total : '',
                $line['reglement'],
                $line['comment'],
            ],
            $csvDelimiter,
            $csvEnclosure
        );
    }

    fclose($fp);

    exit;
}

/*
 * This action is used in AJAX in order to update "compta" data.
 * Only 4 columns are available for update:
 *  - categorie
 *  - reglement
 *  - evenement
 *  - comment
 * The new value is passed with the `val` variable (POST).
 * The column and the "compta" identifier are passed with GET vars.
 *
 * There is no content return on failure, only headers.
 * If the update succeed we display a simple JSON element with a 200 status code.
 *
 * This action is added to perform Ajax updates directly on the "journal" list
 * in order to improve utilization.
 */
elseif ($action === 'modifier_colonne') {

    try {
        // Bad request?
        if (!isset($_POST['val']) || !isset($_GET['column']) || !isset($_GET['id']) || !($line = $compta->obtenir($_GET['id']))) {
            throw new Exception("Please verify parameters", 400);
        }

        // Test line existence
        if (!$line['id']) {
            throw new Exception("Not found", 404);
        }

        $allowEmpty = false;

        switch ($_GET['column']) {
            case 'categorie':
                $column = 'idcategorie';
                $value  = (int) $_POST['val'];
                break;
            case 'reglement':
                $column = 'idmode_regl';
                $value  = (int) $_POST['val'];
                break;
            case 'evenement':
                $column = 'idevenement';
                $value  = (int) $_POST['val'];
                break;
            case 'comment':
                $column = 'comment';
                $value  = (string) $_POST['val'];
                $allowEmpty = true;
                break;
            default:
                throw new Exception("Bad column name", 400);
        }

        // No value?
        if (!$allowEmpty && !$value) {
            throw new Exception("Bad value", 400);
        }

        if ($compta->modifierColonne($line['id'], $column, $value)) {
            $response = [
                'success' => true,
            ];

            // Done!
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 200 OK');
            die(json_encode($response));
        } else {
            throw new Exception("An error occurred", 409);
        }
    } catch (Exception $e) {
        switch ($e->getCode()) {
            case 404:
                $httpStatus = "Not Found";
                break;
            case 400:
                $httpStatus = "Bad Request";
                break;
            case 409:
                $httpStatus = "Conflict";
                break;
        }
        header('HTTP/1.1 ' . $e->getCode() . ' ' . $httpStatus);
        header('X-Info: ' . $e->getMessage());
        exit;
    }

} elseif ($action == 'supprimer') {
    if ($compta->supprimerEcriture($_GET['id']) ) {
        AFUP_Logs::log('Suppression de l\'écriture ' . $_GET['id']);
        afficherMessage('L\'écriture a été supprimée', 'index.php?page=compta_journal&action=lister');
    } else {
        afficherMessage('Une erreur est survenue lors de la suppression de l\'écriture', 'index.php?page=compta_journal&action=lister', true);
    }
} elseif ($action == 'importer') {
    $formulaire = &instancierFormulaire();
	$formulaire->addElement('header', null          , 'Import CSV');
    $formulaire->addElement('file', 'fichiercsv', 'Fichier banque'     );

	$formulaire->addElement('header', 'boutons'  , '');
	$formulaire->addElement('submit', 'soumettre', 'Soumettre');

    if ($formulaire->validate()) {
		$valeurs = $formulaire->exportValues();
        $file =& $formulaire->getElement('fichiercsv');
        $tmpDir = dirname(__FILE__) . '/../../../tmp';
        if ($file->isUploadedFile()) {
            $file->moveUploadedFile($tmpDir, 'banque.csv');
            $lignes = file($tmpDir . '/banque.csv');
            if ($compta->extraireComptaDepuisCSVBanque($lignes)) {
                AFUP_Logs::log('Chargement fichier banque');
                afficherMessage('Le fichier a été importé', 'index.php?page=compta_journal&action=lister');
            } else {
                afficherMessage('Le fichier n\'a pas été importé', 'index.php?page=compta_journal&action=lister', true);
            }
            unlink($tmpDir . '/banque.csv');
        }
    }
    $smarty->assign('formulaire', genererFormulaire($formulaire));
} elseif ($action == 'ventiler') {
    $idCompta = (int)$_GET['id'];
    $montant = (float) $_GET['montant'];
    $ligneCompta = $compta->obtenir($idCompta);
    $compta->ajouter($ligneCompta['idoperation'],
                     $ligneCompta['idcompte'],
                     26, // A déterminer
                     $ligneCompta['date_ecriture'],
                     $ligneCompta['nom_frs'],
                     $montant,
                     $ligneCompta['description'],
                     $ligneCompta['numero'],
                     $ligneCompta['idmode_regl'],
                     $ligneCompta['date_regl'],
                     $ligneCompta['obs_regl'],
                     8, // A déterminer
                     $ligneCompta['numero_operation']);
    $compta->modifier($ligneCompta['id'],
                      $ligneCompta['idoperation'],
                      $ligneCompta['idcompte'],
                      $ligneCompta['idcategorie'],
                      $ligneCompta['date_ecriture'],
                      $ligneCompta['nom_frs'],
                      $ligneCompta['montant'] - $montant,
                      $ligneCompta['description'],
                      $ligneCompta['numero'],
                      $ligneCompta['idmode_regl'],
                      $ligneCompta['date_regl'],
                      $ligneCompta['obs_regl'],
                      $ligneCompta['idevenement'],
                      $ligneCompta['numero_operation']);
    afficherMessage('L\'écriture a été ventilée', 'index.php?page=compta_journal&action=modifier&id=' . $compta->lastId);
}
