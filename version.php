<?php
require('../../config.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url(new moodle_url('/local/json2activity/index.php', ['courseid'=>$courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title(get_string('pluginname', 'local_json2activity'));
$PAGE->set_heading(format_string(get_course($courseid)->fullname));

require_once($CFG->dirroot.'/local/json2activity/classes/form/importform.php');
require_once($CFG->dirroot.'/course/modlib.php');
require_once($CFG->dirroot.'/course/lib.php');

$mform = new \local_json2activity\form\importform(null, ['courseid' => $courseid]);

echo $OUTPUT->header();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id'=>$courseid]));
} else if ($data = $mform->get_data()) {
    require_sesskey();

    try {
        // 1) Décoder : on n'accepte que des LISTES (array) d'items.
        $items = json_decode($data->json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($items)) {
            throw new moodle_exception('Input must be a JSON array of items.');
        }

        $course = get_course($courseid);

        // 2) Préparer les sections nécessaires d'un coup.
        $sections = [];
        foreach ($items as $idx => $it) {
            $sec = isset($it['section']) ? (int)$it['section'] : 0;
            $sections[] = max(0, $sec);
        }
        $sections = array_values(array_unique($sections));
        if ($sections) {
            course_create_sections_if_missing($course->id, $sections);
        }

        // 3) Récupérer l'ID du module 'label' (une fois pour toutes).
        global $DB;
        $labelmoduleid = (int)$DB->get_field('modules', 'id', ['name' => 'label'], MUST_EXIST);

        $created = [];
        $errors  = [];

        // (Optionnel debug SQL)
        // $DB->set_debug(true);

        // 4) Traiter chaque item.
        foreach ($items as $i => $it) {
            try {
                // Validation minimale par item
                if (empty($it['activity']) || !is_array($it['activity'])) {
                    throw new moodle_exception("Item #$i: missing 'activity' object");
                }
                $act = $it['activity'];

                if (empty($act['type']) || $act['type'] !== 'label') {
                    throw new moodle_exception("Item #$i: only 'label' type is supported");
                }
                if (empty($act['html'])) {
                    throw new moodle_exception("Item #$i: missing 'activity.html'");
                }

                $sectionnum = isset($it['section']) ? max(0, (int)$it['section']) : 0;

                // (facultatif) vérifier qu'on peut bien ajouter ce module dans cette section
                // can_add_moduleinfo($course, 'label', $sectionnum);

                // 5) Construire l'objet attendu par add_moduleinfo() — version qui fonctionne chez toi.
                $mi = new stdClass();
                $mi->modulename          = 'label';
                $mi->module              = $labelmoduleid;     // ⚠️ indispensable
                $mi->coursemodule        = 0;                  // nouveau CM
                $mi->course              = $course->id;
                $mi->section             = $sectionnum;        // numéro de section (pas l'id)
                $mi->cmidnumber          = '';
                $mi->groupmode           = $course->groupmode ?? 0;
                $mi->groupingid          = 0;
                $mi->availability        = null;               // ou chaîne JSON valide si besoin
                $mi->completion          = !empty($course->enablecompletion) ? 1 : 0;

                $mi->intro               = $act['html'] ?? '';
                $mi->introformat         = FORMAT_HTML;
                $mi->visible             = isset($act['visible']) ? (int)!empty($act['visible']) : 1;
                $mi->visibleoncoursepage = 1;
                $mi->showdescription     = 0;

                // 6) Créer
                $res = add_moduleinfo($mi, $course, null); // renvoie un objet avec ->coursemodule, ->instance, etc.
                $created[] = ['index'=>$i, 'cmid'=>$res->coursemodule, 'section'=>$sectionnum];

            } catch (\Throwable $e) {
                $errors[] = "Item #$i: ".$e->getMessage();
            }
        }

        // 7) Feedback utilisateur
        if ($created) {
            \core\notification::success(count($created).' label(s) créé(s).');
        }
        if ($errors) {
            foreach ($errors as $msg) {
                \core\notification::error($msg);
            }
            // On reste sur la page pour afficher les erreurs.
        } else {
            // Tout est OK → retour au cours.
            redirect(new moodle_url('/course/view.php', ['id' => $course->id]), '', 0);
        }

    } catch (\Throwable $e) {
        \core\notification::error('Erreur: '.$e->getMessage());
    }
}

$mform->display();
echo $OUTPUT->footer();
?>