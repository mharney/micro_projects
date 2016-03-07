<?php
//error_log("===== API call started =====");
require_once 'boot.php';

/*
 *  API Interface
 *
 *  This file only accessible by only and only valid
 *  - AJAX + POST request with required PARAMETERS
 *  - By Requiring this file to any other script
 */
if ( str_replace('/', '', $_SERVER['SCRIPT_NAME']) === 'api.php'
    && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'PUT', 'DELETE']) )
{
    die('Error');
}

/*
 *  Deter mine if the request is POST and AJAX, then pass request through API security protocol
 */
if ( !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {

    header('Content-type: application/json');

    // PHP transforms multipart/form-data by default,
    // but we need to interpret the raw body as JSON
        $post_data = json_decode(file_get_contents("php://input"), true);
    //error_log("post data: ".print_r($post_data,true));
    $api = new API($post_data);

    echo $api->return_json;
}

/**
 *
 * The Gentleman's API
 *
 * Expects a call like:
 * POST api/ { method: setGrade, content: { "student" : 2893, "assignment": 3297, "points": 93 } }
 *
 */
class API {

    public $return_json;
    public $content;
    public $method;

    # Expand and process the JSON formatted save request

    public function __construct($request) {
        # Throw an error if we haven't received a valid save method and some kind of content
        if (!isset($request['method'])) {
            return $this->return_json = $this->get_error_obj("Error: `method` and `content` are required.");
        } else {

            $this->method = $request['method'];
            $this->content = $request['content'];

            // Log Call

            $possible_calls = [

                //  method for grade book
                'getAllGradeScales',
                'getAllGradeBookData',
                'getMetaDataForGradeBook',
                'getSectionInfo',
                'getSectionAssignments',
                'setGrade',
                'getGradesList',
                'updateGradeScale',
                'getGradingPeriods',
                'saveStudentFinalGrade',
                'setSectionWeightedState',
                'setSectionRoundingStatus',
                'getSectionWeightedState',
                'getSectionRoundingStatus',
                'getGradingComments',
                'setManyGrades',
                'saveManyStudentsFinalGrade',

                //  methods for GradeBook Standard View
                'saveStudentGradeForStandardMode',
                'setManyStandardGrades',
                'getCategoriesAndStandards',
                'getGradesListForStandardView',

                //  methods for Assignment
                'getAllCategories',
                'getDropScoresBySection',
                'deleteDropScoreRecord',
                'toggleDropByAssignment',

                //  methods for Assessment Builder
                'getAssessment',
                'setAssessment',
                'deleteAssessment',
                'searchQuestionForBuilder',
                'importSelectedQuestions',

                //  methods for Content Set Builder One (For Flashcard)
                'setDataSetOne',
                'getDataSetOne',
                'deleteDataSetOne',

                //  methods for Content Set Builder Two (For Flashcard)
                'setDataSetTwo',
                'getDataSetTwo',
                'deleteDataSetTwo',

                //  methods for Flashcard Creator
                'getDataSets',
                'getDataSetFields',
                'setFlashCardContent',
                'setFlashCardLayout',
                'getFlashCard',

                //  methods for Flashcard Viewer
                'getFlashcardViewer',

                //  methods for Medical Professionals
                'getPracticeAreas',
                'getMedicalProfessionals',
                'setMedicalProfessional',
                'getMedicalProfessionalsUser',
                'setMedicalProfessionalToUser',
                'removeProfessionalFromUser',

                //  methods for Flashcard Quiz
                'getAllFlashcardsByAuthUser',
                'getFlashcardQuiz',

                //  Common methods
                'getUserTitles',

                //scheduler
                'getCourseListDetails',
                'getAllCourseDetails',
                'getStudentsByCourse',
                'saveSchedulerTimeSlot',
                'eraseScheduledTimeslot',
                'getViewModelCourseDetail',

                // report card generator
                'getAllGradingPeriods',
                'getAllGradeLevels',
                'getAllGPACalculations',

                //  methods for Assessment Taker
                'getTakerAssessments',
                'setStudentAssessmentAnswers',

                // updating attendance copying
                'updateAttendanceCopyingCheckbox',

                // updating grade distribution
                'updateGradeDistributionCheckbox',

                // updating show overall grade
                'updateShowOverallGradeCheckbox',

                // updating show aggregate periods
                'updateShowAggregatePeriodCheckbox',

                // delete all requests from courses with too few
                'tooFewDeleteAllRequests',

                // Widgets and Tabs
                'deleteWidgetFromPage'
            ];

            # Validate that we have the requested method setup
            if (!in_array($this->method, $possible_calls)) {
                return $this->return_json = $this->get_error_obj("Error: Method $this->method does not exist.");
            }


            # Run the call
            $this->return_json = json_encode(call_user_func("self::$this->method", $this->content), JSON_NUMERIC_CHECK);
        }


        # Return JSON data when an POST + AJAX request
        if ( !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {

        }

        # By default return API instance
        return $this->return_json;

    }

    # Delete a widget from a Page
    public function deleteWidgetFromPage($content) {
        $content = escape_all($content);
        $response = TscWidgetsAndTabs::deleteWidgetFromPage($content);
        return $response;
    }

    # Delete the previous grade entry and set the new grade
    public function setGrade($content) {
        if (!is_array($content)) {
            return $this->return_json = $this->get_error_obj("Error: The JSON submitted appears to be malformed, possibly missing a key or value.");
        }

        # Validate the fields/values of the content array
        $require_fields = ['student_id', 'assignment_id', 'points'];
        $optional_fields = ['comment', 'submit_date', 'original_grade', 'is_drop', 'drop_type', 'docked_grade'];
        # Any extra unknown keys?
        foreach ($content as $k => $v) {
            if (!in_array($k, array_merge($require_fields, $optional_fields))) {
                return $this->return_json = $this->get_error_obj("Error: `$k` is not a valid property of the setGrade method.");
            }
        }
        # Any missing keys?
        foreach ($require_fields as $k => $v) {
            if (!array_key_exists($v, $content)) {
                return $this->return_json = $this->get_error_obj("Error: A value must be passed for `$k` with the setGrade method.");
            }
        }

        $user_id = filter_var($content['student_id'], FILTER_SANITIZE_NUMBER_INT);
        $assignment_id = filter_var($content['assignment_id'], FILTER_SANITIZE_NUMBER_INT);
        $grade = is_null($content['points']) ? 'NULL' : escape($content['points']) ; //filter_var($content['points'], FILTER_SANITIZE_NUMBER_FLOAT);
        $comment = filter_var($content['comment'], FILTER_SANITIZE_STRING);
        $comment = $comment ? '"' . escape($comment) . '"' : 'NULL';
        $submit_date = !empty($content['submit_date']) ? escape($content['submit_date']) : null;
        $original_grade = !empty($content['original_grade']) ? $content['original_grade'] : 0;
        $is_drop = !$content['is_drop'] ? 0 : 1;
        $drop_type = !empty($content['drop_type']) ? $content['drop_type'] : null;
        if ($_id = GradeBooks::setGrade($user_id, $assignment_id, $grade, $comment, $submit_date, $original_grade, $is_drop, $drop_type)) {
            $return_json = $_id;
        } else {
            $return_json = $this->get_error_obj("Error: The grade could not be set.");
        }
        return $return_json;
    }

    public function setManyGrades($contents) {
        $res = [];
        foreach($contents as $content) {
            $res[] = API::setGrade($content);
        }
        return $res;
    }

    public function setSectionGrade($content) {

        $user_id = filter_var($content['student_id'], FILTER_SANITIZE_NUMBER_INT);
        $section_id = filter_var($content['section_id'], FILTER_SANITIZE_NUMBER_INT);
        $grade_id = filter_var($content['grade_id'], FILTER_SANITIZE_NUMBER_INT);

        if (!is_array($content)) {
            return $this->return_json = $this->get_error_obj("Error: The JSON submitted appears to be malformed, possibly missing a key or value.");
        }

        if (GradeBooks::set_section_grade($user_id, $section_id, $grade_id)) {
            $return_json = ["success" => true];
        } else {
            $return_json = $this->get_error_obj("Error: The section grade could not be set.");
        }
        return $return_json;
    }

    public function getSectionInfo($params) {
        $section_id = $params['section_id'];

        // TODO:
        // This clutter makes a strong case for a database ORM layer.
        // An ORM would allow such objects to be composed in a clean and persistent way.
        // Mapping database columns to object fields over and over for each query is pretty nasty.
        // See https://docs.djangoproject.com/en/dev/topics/db/models/ for a good intro
        $sql = "select
                s.id as section_id,
                g.name as section_term,
                concat(b.name, ' ', r.num) as section_location,
                p.id as period_id,
                p.name as period_name,
                'MWF 3:00-4:05 PM' as period_description,
                c.id as course_id,
                c.name as course_title,
                d.name as course_department,
                c.description as course_description,
                u.id as teacher_id,
                u.first_name as teacher_firstname,
                u.last_name as teacher_lastname
                from sections s
                left join courses c on c.id = s.course_id
                left join rooms r on r.id = 10
                left join buildings b on b.id = r.building_id
                left join schedule sched on sched.section_id = s.id and sched.is_teacher = 1
                left join users u on u.id = sched.user_id
                left join periods p on p.id = s.period
                left join course_groups d on d.id = 1
                left join grading_periods g on g.id = s.term
                where s.id =  $section_id";
        $rs = API::query($sql, __LINE__);
        $r = $rs[0];
        $data = [
            'id' => $r['section_id'],
            'term' => $r['section_term'],
            'location' => $r['section_location'],
            'period' => array(
                'id' => $r['period_id'],
                'name' => $r['period_name'],
                'description' => $r['period_description'],
            ),
            'course' => array(
                'id' => $r['course_id'],
                'title' => $r['course_title'],
                'department' => $r['course_department'],
                'description' => utf8_encode($r['course_description']),
            ),
            'teacher' => array(
                'id' => $r['teacher_id'],
                'firstName' => $r['teacher_firstname'],
                'lastName' => $r['teacher_lastname'],
            ),
        ];

        return $data;
    }

    public static function getSectionAssignments($content) {
        $section_id = $content['section_id'];
        $is_gradebook = !empty($content['is_gradebook']);
        $date_start = $content['date_start'];
        $date_end = $content['date_end'];

        $json_template = [
            'assessment_idx' => [],
            'assignments' => []
        ];

        $assignments = Assignments::get_assignments($section_id, $is_gradebook, $date_start, $date_end);

        if ( !empty($assignments) ) {
            //$assessment_idx = do_extract($assignments, 'assessmentId');
            //$json_template['assessment_idx'] = $assessment_idx;

            $json_template['assignments'] = [];

            if( !empty($assignments) ) {
                foreach ($assignments as $assignment) {
                    $json_template['assignments'][] = $assignment;
                }
            }
        }

        return $json_template;
    }

    //  api interface for get list of grades for a section
    public function getGradesList($params, $preserve_index = false) {
        $section_id = $params['section_id'];
        return GradeBooks::getGradesList($section_id, $preserve_index);
    }

    //  api interface for update grade scale by teacher
    //  in grade book page (Grade Scale modal)

    public function updateGradeScale($content) {
        $res = GradeBooks::updateGradeScale($content);
        return $res;
    }

    public function getMedicalProfessionalsUser($content) {

        $json_template = [
        ];


        $medicalProfessionalsUsers = MedicalProfessionals::get_medical_professionals_users($content);
        $udata = array();
        foreach ($medicalProfessionalsUsers as $medicalProfessionalsUsers) {

            if ($medicalProfessionalsUsers['field'] == 'phone' && $medicalProfessionalsUsers['primary_contact'] == '1') {
                $udata[$medicalProfessionalsUsers['userId']]['primary_phone'] = $medicalProfessionalsUsers['value'];
            } else {
                $udata[$medicalProfessionalsUsers['userId']]['contact'][] = $medicalProfessionalsUsers;
            }

            $udata[$medicalProfessionalsUsers['userId']]['id'] = $medicalProfessionalsUsers['pkey'];
            $udata[$medicalProfessionalsUsers['userId']]['userId'] = $medicalProfessionalsUsers['userId'];
            $udata[$medicalProfessionalsUsers['userId']]['first_name'] = $medicalProfessionalsUsers['first_name'];
            $udata[$medicalProfessionalsUsers['userId']]['last_name'] = $medicalProfessionalsUsers['last_name'];
            $udata[$medicalProfessionalsUsers['userId']]['practice_area'] = $medicalProfessionalsUsers['practice_area'];
            // $udata[$medicalProfessionalsUsers['userId']]['contact'][] = $medicalProfessionalsUsers;
        }

        foreach ($udata as $mudata) {
            if (!isset($mudata['primary_phone'])) {
                $mudata['primary_phone'] = 'N/A';
            }
            $json_template['medicalProfessionals'][] = $mudata;
        }


        return $json_template;
    }

    public function setMedicalProfessional($content) {

        return [
            "success" => MedicalProfessionals::set_medical_professionals($content),
        ];
    }

    public function setMedicalProfessionalToUser($content) {

        return [
            "success" => MedicalProfessionals::set_medical_professional_users($content),
        ];
    }

    public function removeProfessionalFromUser($content) {

        return [
            "success" => MedicalProfessionals::remove_medical_professional_from_user($content),
        ];
    }

    public function getUserTitles() {
        $json_template['userTitles'] = null;
        $titles = MedicalProfessionals::get_user_titles();
        foreach ($titles as $title) {
            $json_template['userTitles'][] = $title;
        }
        return $json_template;
    }

    public function getMedicalProfessionals($content) {

        $json = [
        ];

        $medicalProfessionals = MedicalProfessionals::get_medical_professionals($content);
        $json_template['medicalProfessionals'] = null;

        foreach ($medicalProfessionals as $medicalProfessionals) {
            $json_template[$medicalProfessionals['mp_user_id']]['pname'] = $medicalProfessionals['title'] . " " . $medicalProfessionals['first_name'] . " " . $medicalProfessionals['last_name'] . " \n" . $medicalProfessionals['practice_name'];
            $json_template[$medicalProfessionals['mp_user_id']]['user_id'] = $medicalProfessionals['user_id'];
            $json_template[$medicalProfessionals['mp_user_id']]['id'] = $medicalProfessionals['id'];
            $json_template[$medicalProfessionals['mp_user_id']]['pkey'] = $medicalProfessionals['pkey'];
            $json_template[$medicalProfessionals['mp_user_id']]['first_name'] = $medicalProfessionals['first_name'];
            $json_template[$medicalProfessionals['mp_user_id']]['last_name'] = $medicalProfessionals['last_name'];
            $json_template[$medicalProfessionals['mp_user_id']]['users'][] = $medicalProfessionals;
            // $json_template[$medicalProfessionals['mp_user_id']]['users'][$medicalProfessionals['user_id']]['last_name'] = $medicalProfessionals['user_last_name'];
        }

        foreach ($json_template as $k => $data) {
            if (!empty($data))
                $json['medicalProfessionals'][] = $data;
        }

        return $json;
    }

    public function getPracticeAreas() {

        $json_template = [
            "results" => 10,
            "offset" => 0,
            "limit" => 10
        ];


        $practiceAreas = MedicalProfessionals::get_practice_areas();
        $json_template['practiceAreas'] = null;
        foreach ($practiceAreas as $practiceArea) {
            $json_template['practiceAreas'][] = $practiceArea;
        }

        return $json_template;
    }

    // NEW GRADING PERIOD STUDENTS RETRIEVE METHOD
    public static function getGradeBookStudents($content) {
        $section_id = $content['section_id'];
        $grading_period_id = $content['grading_period_id'];
        $grading_scale_id = $content['grading_scale_id'];
        $is_standard = $content['is_standard'];
        $grading_scales = $content['grading_scales'];

        // Find out if the grading period is open or closed
        $sql = "SELECT CASE WHEN NOW() BETWEEN date_start AND date_end THEN 1 ELSE 0 END as is_open FROM grading_periods WHERE id = $grading_period_id";
        $res = query($sql,__LINE__);
        $row = array_pop($res);
        $status_sql = $row['is_open'] == 1 ? "sched.status_id" : "1 as status_id";

        // one query, for students and their final grades
        $sql = "SELECT
                    CASE
                WHEN u.nickname IS NULL THEN
                    concat(
                        u.last_name,
                        ', ',
                        u.first_name
                    )
                ELSE
                    concat(
                        u.last_name,
                        ', ',
                        u.nickname
                    )
                END AS name,
                 u.id,
                 u.id student_id,
                 u.gender,
                 $status_sql,
                 gf.id AS grade_final_id,
                 gf.grade_id,
                 gf.is_override AS is_overridden,
                 gf.overall_percent,
                 IF(gf.is_override = 1, gf.overall_percent, null) override_percent,
                 IF(gf.is_override = 1, gf.grade_id, null) override_grade_id,
                 gf.comments AS overall_grade_comments
                FROM users u
                LEFT JOIN `schedule` sched ON sched.user_id = u.id AND sched.section_id = $section_id AND sched.status_id = 1
                LEFT JOIN grades_final gf ON gf.student_id = u.id AND gf.section_id = $section_id
                    AND gf.grading_period_id = $grading_period_id
                    AND gf.grading_scale_id = $grading_scale_id
                    AND gf.is_standard = $is_standard
                WHERE
                    u.id IN (
                        SELECT user_id
                        FROM `schedule`
                        WHERE section_id = $section_id
                        AND is_teacher = 0
                    )
                ORDER BY
                    u.last_name,
                    u.first_name";

        $students = query($sql, __LINE__);

        array_walk($students, function (&$student) use ($content, $grading_scales) {
            $student['letter'] = GradeBooks::get_grade_letter_from_map($grading_scales, $student['grade_id']);
            $student = array_merge($student, [
                'grades' => [],
                'assignments_files' => '',
                'assessments' => []
            ]);
            return $student;
        });

        return $students;
    }

    public function get_error_obj($notice_text) {
        return $error_template = '{status: "error", message: "' . $notice_text . '" }';
    }

    private function authenticate_request() {
        return true;
    }

    public static function query($sql, $line) {
        global $conn;
        //error_log("TIMESTAMP: " . time() . " -- $sql \n");
        $res = mysqli_query($conn, $sql) or die(mysqli_error_fn($line . " \n<pre>" . $sql . "</pre>"));
        if (is_object($res))
            if ($res->num_rows) {
                $rows = [];
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
                if (!empty($rows)) {
                    return $rows;
                }
                return [];
            }
        return [];
    }

    //  api interface to assessment set
    public function setAssessment($content) {
        if (!is_array($content)) {
            return $this->return_json = $this->get_error_obj("Error: The JSON submitted appears to be malformed, possibly missing a key or value.");
        }

        $res = Assessment::setAssessment($content);
        if ($res) {
            $return_json = ['success' => true, 'data' => $res];
        } else {
            $return_json = $this->get_error_obj("Error: The assessment could not be set.");
        }
        return $return_json;
    }

    // api interface for get assessment
    // object using passed assessment_id
    public function getAssessment($params) {
        $assessment_id = $params['assessment_id'];
        $assessment_view_model = Assessment::getAssessment($assessment_id);
        return $assessment_view_model;
    }

    // api interface to delete assessment
    public function deleteAssessment($params) {
        $delete_assessment_status = Assessment::deleteAssessment($params);
        return $delete_assessment_status;
    }

    // api interface to set data set one
    public function setDataSetOne($content) {
        if (!is_array($content)) {
            return $this->return_json = $this->get_error_obj("Error: The JSON submitted appears to be malformed, possibly missing a key or value.");
        }

        $res = DataSetBuilderOne::setDataSetOne($content);
        if ($res) {
            $return_json = $res;
        } else {
            $return_json = $this->get_error_obj("Error: The assessment could not be set.");
        }
        return $return_json;
    }

    // api interface to retrieve data set one
    // required a valid data set `id`
    public function getDataSetOne($params) {
        $data_set_one_id = $params['dataset_id'];
        $data_set_view_model = DataSetBuilderOne::getDataSetOne($data_set_one_id);
        return $data_set_view_model;
    }

    // api interface to delete data set one
    // required a valid data set `id`
    public function deleteDataSetOne($params) {
        $data_set_one_field_id = $params['data_set_one_field_id'];
        $delete_data_set_status = DataSetBuilderOne::deleteDataSetOne($data_set_one_field_id);
        return ['success' => $delete_data_set_status];
    }

    // api interface to set data set two items
    public function setDataSetTwo($content) {
        if (!is_array($content)) {
            return $this->return_json = $this->get_error_obj("Error: The JSON submitted appears to be malformed, possibly missing a key or value.");
        }

        $res = DataSetBuilderTwo::setDataSetTwo($content);
        if ($res) {
            $return_json = $res;
        } else {
            $return_json = $this->get_error_obj("Error: The assessment could not be set.");
        }
        return $return_json;
    }

    // api interface to retrieve data set two items
    // required a valid data set `id`
    public function getDataSetTwo($params) {
        $dataset_id = $params['dataset_id'];
        $data_set_view_model = DataSetBuilderTwo::getDataSetTwo($dataset_id);
        return $data_set_view_model;
    }

    // api interface to delete data set two item
    // required a valid data set `id`
    public function deleteDataSetTwo($params) {
        $dataset_item_id = $params['dataset_item_id'];
        $delete_data_set_status = DataSetBuilderTwo::deleteDataSetTwo($dataset_item_id);
        return ['success' => $delete_data_set_status];
    }

    //  api interface to access data set list for Flash card creator (modal 1)
    //  for Logged In user
    public function getDataSets() {
        $res = FlashCardCreator::getDataSets();
        return $res;
    }

    //  api interface to get all fields for a data set for flash card creator (modal 2)
    //  a valid $dataset_id required
    public function getDataSetFields($params) {
        $dataset_id = escape($params['dataset_id']);
        $res = FlashCardCreator::getDataSetFields($dataset_id);
        return $res;
    }

    //  api interface to save flashcard layout (from model 2)
    public function setFlashCardLayout($content) {
        $res = FlashCardCreator::setFlashCardLayout($content);
        return $res;
    }

    //  api interface for get all completed cards for flashcard creator page (left column)
    public function getFlashCard($params) {
        $flashcard_id = $params['flashcard_id'];
        $res = FlashCardCreator::getFlashCard($flashcard_id);
        return $res;
    }

    //  api interface for get all Flashcard viewer items
    public function getFlashcardViewer($params) {
        $flashcard_id = $params['flashcard_id'];
        $res = FlashcardViewer::getFlashcardViewer($flashcard_id);
        return $res;
    }

    //  api interface for get all Flashcard quiz
    //  of specific flash card
    public function getFlashcardQuiz($params) {
        $flashcard_id = $params['flashcard_id'];
        $res = FlashcardQuiz::getFlashcardQuiz($flashcard_id);
        return $res;
    }

    //  api interface for get all Flashcards list of auth user
    public function getAllFlashcardsByAuthUser() {
        return FlashcardQuiz::getAllFlashcardsByAuthUser();
    }

    //  api interface for get question search result for assessment builder
    public function searchQuestionForBuilder($content) {
        $form_data = $content['form_data'];
        return Assessment::searchQuestionForBuilder($form_data);
    }

    //  api interface for get selected question after search in assessment builder
    public function importSelectedQuestions($content) {
        return Assessment::importSelectedQuestions($content);
    }

    //  api interface for get all assignment categories under a section
    public function getAllCategories($content){
        return Assignments::getAllCategories($content);
    }

    //  api interface for get all drop scores for a section
    public function getDropScoresBySection($content){
        return Assignments::getDropScoresBySection($content);
    }

    //  api interface for get all drop scores for a section
    public function deleteDropScoreRecord($content){
        return Assignments::deleteDropScoreRecord($content);
    }

    //  api interface for toggle All score drop by assignment
    public function toggleDropByAssignment($content) {
        return Assignments::toggleDropByAssignment($content);
    }

    //  api interface for getting all grading periods
    public function getGradingPeriods($params) {
        $section_id = $params['section_id'];
        return GradeBooks::getGradingPeriods($section_id);
    }

    //  api interface for save student final grade
    public function saveStudentFinalGrade($content) {
        return GradeBooks::saveStudentFinalGrade($content);
    }

    public function setManyStandardGrades($contents) {
        $res = [];
        foreach($contents as $content) {
            $res[] = API::saveStudentGradeForStandardMode($content);
        }
        return $res;
    }

    public function saveStudentGradeForStandardMode($content){
        return GradeBookStandardView::saveStudentGradeForStandardMode($content);
    }

    public function saveManyStudentsFinalGrade($contents) {
        return GradeBooks::saveManyStudentsFinalGrade($contents);
    }

    // api interface for save section weighted/unweighted state
    public function setSectionWeightedState($content) {
        return GradeBooks::setSectionWeightedState($content);
    }

    // api interface for save section floating rounding status
    public function setSectionRoundingStatus($content) {
        return GradeBooks::setSectionRoundingStatus($content);
    }

    // api interface for get the section weighted/unweighted state
    public function getSectionWeightedState($content) {
        return GradeBooks::getSectionWeightedState($content);
    }

    // api interface for get the section floating rounding status
    public function getSectionRoundingStatus($content) {
        return GradeBooks::getSectionRoundingStatus($content);
    }

    // api interface for get the grading comments list
    public function getGradingComments($content) {
        return GradeBooks::getGradingComments($content);
    }

    /* GradeBook Standard View */

    //  api interface for get categories and standards for GradeBook Standard mode
    public function getCategoriesAndStandards($content) {
        return GradeBookStandardView::getCategoriesAndStandards($content);
    }

    //  api interface for get grades for GradeBook Standard Mode
    public function getGradesListForStandardView($content) {
        $section_id = $content['section_id'];
        return GradeBookStandardView::getGradesListForStandardView($section_id);
    }

    // Attendance copying mode
    public function updateAttendanceCopyingCheckbox($content) {
        return AttendanceCopying::update_checkbox($content);
    }

    // Grade distribution chart on/off
    public function updateGradeDistributionCheckbox($content) {
        return GradeDistribution::update_checkbox($content);
    }

    // Grade show overall grade on/off
    public function updateShowOverallGradeCheckbox($content) {
        return ShowOverallGrade::update_checkbox($content);
    }

    // Grade show overall grade on/off
    public function updateShowAggregatePeriodCheckbox($content) {
        return ShowAggregatePeriod::update_checkbox($content);
    }

    // delete all requests from courses with too few
    public function tooFewDeleteAllRequests($content) {
        return deleteAllRequests::delete_requests($content);
    }

    /* SCHEDULER */

    // course list and course details
    public function getCourseListDetails($content) {
        return Scheduler::updateCourseStats();
    }

    // get all courses objects by school_id
    public function getAllCourseDetails($content) {
        $school_id = $content['school_id'];
        return Scheduler::getAllCourseDetails($school_id);
    }

    // get all courses objects by school_id
    public function getStudentsByCourse($content) {
        $course_id = $content['course_id'];
        return Scheduler::getStudentsByCourse($course_id);
    }

    public function saveSchedulerTimeSlot($content){
        $course_id  = $content['course_id'];
        $student_id = $content['student_id'];
        $period_id  = $content['period_id'];
        $term_id    = $content['term_id'];
        $day_id     = $content['day_id'];
        return Scheduler::saveSchedulerTimeSlot($course_id,$student_id,$period_id,$term_id,$day_id);
    }

    public function eraseScheduledTimeslot($content) {
        $section_id = $content['section_id'];
        $student_id = $content['student_id'];
        return Scheduler::eraseScheduledTimeslot($section_id,$student_id);
    }

    public function getViewModelCourseDetail($content){
        return Scheduler::getViewModelCourseDetail($content['course_id']);
    }




    /* Report Card Generator */
    public function getAllGradingPeriods(){
        return ReportCardGenerator::getAllGradingPeriods();
    }

    public function getAllGradeLevels(){
        return ReportCardGenerator::getAllGradeLevels();
    }

    public function getAllGPACalculations(){
        return ReportCardGenerator::getAllGPACalculations();
    }



    /* Assessment Taker */

    //  api interface for get Taker questions
    public function getTakerAssessments($params) {
        $assessment_id = $params['assessment_id'];
        return AssessmentTaker::getTakerAssessments($assessment_id);
    }


    //  api interface to set assessment answer from taker
    public function setStudentAssessmentAnswers($content) {
        if (!is_array($content)) {
            return $this->return_json = $this->get_error_obj("Error: The JSON submitted appears to be malformed,
            possibly missing a key or value.");
        }

        $res = AssessmentTaker::setStudentAssessmentAnswers($content);
        if ($res) {
            $return_json = ['success' => true, 'data' => $res];
        } else {
            $return_json = $this->get_error_obj("Error: The assessment could not be set.");
        }
        return $return_json;
    }

    public static function getAllGradeScales($content) {
        $grading_scale_id = $content['grading_scale_id'];
        $sql = "SELECT g.id, g.name, gsm.min_score, gsm.is_override_only
                FROM grades g
                JOIN grades_scales_map gsm ON gsm.grade_id = g.id
                WHERE gsm.grading_scale_id = $grading_scale_id
                ORDER BY gsm.min_score desc";
        $res = query($sql, __LINE__);
        return $res;
    }

    public function getMetaDataForGradeBook($content) {
        $weighted = API::getSectionWeightedState($content);
        $colors = GradeBooks::getColors($content['section_id']);
        $is_rounding = $content['grading_periods'][$content['current_grading_period_id']]['is_rounded']; //API::getSectionRoundingStatus($content);

        $meta = [
            'isRounding' => $is_rounding,
            'isWeighted' => $weighted,
            'colors' => $colors
        ];

        # For Assignment Based GradeBook
        if ( !$content['is_standard'] ) {
            $comments = API::getGradingComments($content);
            $assignment_categories = API::getAllCategories($content);
            $drop_scores_by_section = API::getDropScoresBySection($content);
            $weights = array_map(function($i){ return [$i['id'] => $i['weight']]; }, $assignment_categories);
            asort($weights);
            $meta = array_merge($meta, [
                'gradingPeriodComments' => $comments,
                'categories' => $assignment_categories,
                'dropScoresBySection' => $drop_scores_by_section,
                'weights' => $weights
            ]);
        }
        # For Standard Based GradeBook
        else {
            $categories = API::getCategoriesAndStandards($content);
            $meta = array_merge($meta, [
                'standards' => $categories['standards'],
                'categories' => $categories['categories'],
                'weights' => $categories['weights']
            ]);
        }
        return $meta;
    }

    public function getAllGradeBookData($content) {
        global $User;

        $section_id = $content['section_id'];
        $grading_period_id = $content['current_grading_period_id'];

        //  get all meta data
        $meta = API::getMetaDataForGradeBook($content);

        //  add some meta info to content
        $content['is_weighted'] = $meta['isWeighted'];
        $content['is_rounding'] = $meta['isRounding'];

        //  For Assignment Based GradeBook
        if ( !$content['is_standard'] ) {
            $students = [];

            //  get assignments
            $assignments = API::getSectionAssignments($content);
            $assessment_idx = $assignments['assessment_idx'];

            if ( !empty($assignments['assignments']) ) {

                // get students, their grades and final grades
                $students = API::getGradeBookStudents($content);

                //  get assignments
                $add_sql = '';
                $assignment_ids = do_extract($assignments['assignments'], 'id');
                $assignment_idx = implode(',', $assignment_ids);
                if( !empty($assignment_idx) ) $add_sql = " and ag.assignment_id in ($assignment_idx)";

                // process student's Final Grade
                $content['students'] = $students;
                $students_overall_grades = GradeBooks::saveStudentFinalGrade($content);

                array_walk($students, function (&$student) use($add_sql, $assignments, $assessment_idx, $content, $students_overall_grades) {
                    $student_id = $student['id'];

                    // process student's Final Grade
                    /*$data = [
                        'overall_grade_comments' => $student['overall_grade_comments']
                    ];
                    if ( $student['is_override'] ) {
                        $data['is_overridden'] = $student['is_override'];
                        $data['grade_id'] = $student['grade_id'];
                    }
                    $data['students'] = [$student_id];

                    $data = array_merge($data, $content);

                    // capture re-calculated Final grade details and use that
                    $overall_grade = GradeBooks::saveStudentFinalGrade($data);
                    $overall_grade = array_pop($overall_grade);*/
                    $overall_grade = $students_overall_grades[$student_id];
                    $student['letter'] = $overall_grade['letter'];
                    $student['grade_id'] = $overall_grade['grade_id'];
                    $student['is_override'] = $overall_grade['is_overridden'];
                    $student['override_grade_id'] = $overall_grade['is_overridden'] ? $overall_grade['grade_id'] : null;
                    $student['overall_percent'] = $overall_grade['overall_percent'];
                    $student['override_percent'] = $overall_grade['is_overridden'] ? $overall_grade['overall_percent'] : null;
                    $student['overall_grade_comments'] = $overall_grade['overall_grade_comments'];

                    //  get students grades
                    $sql = "select
                                ag.assignment_id,
                                ag.grade,
                                ag.original_grade,
                                ag.notes as comment,
                                ag.is_drop,
                                ag.drop_type,
                                ag.user_id,
                                if(ag.date_submitted = NULL, NULL, DATE_FORMAT(ag.date_submitted,'%Y-%m-%d')) date_submitted,
                                if(af.google_file IS NULL, '', af.google_file) as handin_google_file
                            from
                                assignment_grades ag
                            left join
                                assignments_files af on af.assignment_id = ag.assignment_id and af.user_id = $student_id
                            where
                                ag.user_id = $student_id
                                $add_sql;";
                    $grades = query($sql, __LINE__);
                    $assignments_grades = [];

                    foreach ($grades as $grade) {
                        $handin_google_file = '';
                        if ( !empty($grade['handin_google_file']) ) {
                            $file = json_decode($grade['handin_google_file'], 1);
                            $handin_google_file = $file['alternateLink'];
                        }
                        $assignments_grades[$grade['assignment_id']] = array(
                            'grade' => $grade['grade'],
                            'original_grade' => $grade['original_grade'],
                            'comment' => $grade['comment'],
                            'date_submitted' => $grade['date_submitted'],
                            'is_drop' => $grade['is_drop'],
                            'drop_type' => $grade['drop_type'],
                            'handin_google_file' => $handin_google_file
                        );
                    }

                    $grades_list = [];
                    $assignments = $assignments['assignments'];
                    if (!empty($assignments)) {
                        foreach ($assignments as $assignment) {
                            if (array_key_exists($assignment['id'], $assignments_grades)) {
                                $grades_list[] = $assignments_grades[$assignment['id']];
                            } else {
                                $grades_list[] = [
                                    'grade' => null,
                                    'original_grade' => null,
                                    'comment' => null,
                                    'date_submitted' => null,
                                    'is_drop' => null,
                                    'drop_type' => null,
                                    'handin_google_file' => ''
                                ];
                            }
                        }
                    }
                    $student['grades'] = $grades_list;
                    $student['assessments'] = Assessment::earnedPoints($student_id, $assessment_idx);
                    return $student;
                });
            }

            //  prepare results
            $results = [
                'isWeighted' => $meta['isWeighted'],
                'isRounding' => $meta['isRounding'],
                'gradingPeriodComments' => $meta['gradingPeriodComments'],
                'assignmentCategories' => $meta['categories'],
                'dropScoresBySection' => $meta['dropScoresBySection'],
                'weights' => $meta['weights'],
                'gradeColors' => $meta['colors'],
                'assignments' => $assignments['assignments'],
                'assessment_idx' => $assessment_idx,
                'students' => array_values($students),
                'totalStudents' => count($students)
            ];
        }
        //  For Standard Based GradeBook
        else {
            $start = microtime(true);
            $students = API::getGradeBookStudents($content);

            # Make sure that all the standards that should be assigned to the course are
            # Get the standards assigned to the course in this grading_period
            $sql = "SELECT DISTINCT standard_id as id
                    FROM courses_standards
                    WHERE course_id in (select course_id from sections where id = $section_id)
                    AND grading_period_id = $grading_period_id";
            $courses_standards = query($sql,__LINE__);
            $courses_standards = array_keys($courses_standards);

            # Get the standards assigned to the section
            $sql = "SELECT DISTINCT standards_categories_id as id
                    FROM assignment_categories
                    WHERE section_id = $section_id
                    AND grading_period_id = $grading_period_id
                    AND is_standard_category = 1";
            $sections_standards = query($sql,__LINE__);
            $sections_standards = array_keys($sections_standards);

            $missing_standards = array_diff($courses_standards,$sections_standards);

            if( !empty($missing_standards) ) {

                $sql = "SELECT course_id FROM sections WHERE id = $section_id";
                $res = query($sql,__LINE__);
                $row = array_pop($res);
                $course_id = $row['course_id'];

                foreach($missing_standards as $standard_id) {
                    $sql = "INSERT INTO assignment_categories
                            (
                              `course_id`,
                              `section_id`,
                              `standards_categories_id`,
                              `is_standard_category`,
                              `grading_period_id`,
                              `created_by`
                            )
                            VALUES
                            (
                              {$course_id},
                              {$section_id},
                              {$standard_id},
                              1,
                              {$grading_period_id},
                              {$User->id}
                            )";
                    save($sql,__LINE__);
                }
            }

            //  get standards
            $standards = $meta['standards'];
            if ( empty($standards) ) {
                $standards = self::getCategoriesAndStandards($content);
                $standards = $standards['standards'];
            }
            //debug('Standards: ' . count($standards) . ' & Students: '. count($students));
            //printf('<h4>Retrieving STANDARDS took: %.5f sec</h4>', (microtime(true) - $start));
            //  re-calculated overall grades
            $content['students'] = $students;
            $overall_grades = GradeBooks::saveStudentFinalGrade($content);

            # Get Grades for Available Standards
            $sql = "SELECT
                        sg.id,
                        sg.standard_id,
                        sg.user_id,
                        sg.notes,
                        sg.grade_id,
                        g.name as grade
                    FROM assignment_categories cat
                    JOIN sections sec ON sec.id = cat.section_id
                    JOIN schedule sched ON sched.section_id = sec.id
                    JOIN section_grading_periods sgp ON sgp.section_id = sec.id AND cat.grading_period_id = sgp.grading_period_id
                    JOIN standards_standards st ON st.id = cat.standards_categories_id
                    LEFT JOIN standards_grades sg ON sg.standard_id = st.id AND sg.user_id = sched.user_id AND sg.grading_period_id = sgp.grading_period_id
                    LEFT JOIN grades g ON g.id = sg.grade_id
                    WHERE sgp.grading_period_id = $grading_period_id
                    AND sg.section_id = $section_id
                    AND cat.is_standard_category = 1";
            //p($sql);
            //debug($sql,1);
            $grades = API::query($sql, __LINE__);
            //printf('<h4>Retrieving GRADES for students and standards took: %.5f sec</h4>', (microtime(true) - $start));
            //debug($grades);

            $start = microtime(true);
            //array_walk($students, function (&$student) use ($overall_grades, $standards, $grades, $content) {
            foreach( $students as $student_id => $student ) {


                $student_id = $student['id'];
                $section_id = $content['section_id'];
                $grading_period_id = $content['grading_period_id'];

                $overall_grade = $overall_grades[$student_id];
                $students[$student_id]['overall_grade_comments'] = $overall_grade['overall_grade_comments'];
                $students[$student_id]['is_overridden'] = $overall_grade['is_overridden'];
                $students[$student_id]['grade_id'] = $overall_grade['grade_id'];
                $students[$student_id]['overall_percent'] = $overall_grade['overall_percent'];
                $students[$student_id]['letter'] = $overall_grade['letter'];

                foreach ($standards as $standard) {
                    $standard_id = $standard['standard_id'];

                    $grade = array_filter($grades, function ($g) use ($student_id, $standard_id) {
                        return $g['user_id'] == $student_id && $g['standard_id'] == $standard_id;
                    });

                    //debug($grade);

                    if (empty($grade)) {
                        $students[$student_id]['grades'][] = [
                            'grade_id' => null,
                            'grading_period_id' => $grading_period_id,
                            'section_id' => $section_id,
                            'standard_id' => $standard_id,
                            'student_id' => $student_id,
                            'grade' => null,
                            'value' => null,
                            'comment' => null
                        ];
                    } else {
                        $grade = array_pop($grade);
                        $students[$student_id]['grades'][] = [
                            'grade_id' => $grade['grade_id'],
                            'grading_period_id' => $grading_period_id,
                            'section_id' => $section_id,
                            'standard_id' => $standard_id,
                            'student_id' => $student_id,
                            'grade' => $grade['grade'],
                            'value' => null,
                            'comment' => $grade['notes']
                        ];
                    }
                }
            }
                //return $student;
            //});

            //printf('<h4>Formatting Students GRADES took: %.5f sec</h4>', (microtime(true) - $start));

            //  prepare results
            $results = [
                'isWeighted' => $meta['isWeighted'],
                'gradeColors' => $meta['colors'],
                'standards' => $meta['standards'],
                'categories' => $meta['categories'],
                'weights' => $meta['weights'],
                'totalStudents' => count($students),
                'students' => array_values($students)
            ];
        }

        return $results;
    }

}

class Scheduler {

    // Get Course ViewModel Details
    public static function getViewModelCourseDetail($course_id){
        $model = [];

        $sql = "SELECT
                c.short_name name, c.id,

                (SELECT count(id) FROM scheduler_sections WHERE course_id = $course_id) num_sections,

                (SELECT count(id) FROM courses_requests WHERE course_id = $course_id) num_requests,

                (SELECT count(ss.id) count from scheduler_sections sse
                    JOIN scheduler_schedule ss on ss.section_id = sse.id
                    WHERE sse.course_id = $course_id) num_enrolled,

                ((SELECT count(id) FROM courses_requests WHERE course_id = $course_id) -
                    (SELECT count(ss.id) count from scheduler_sections sse
                    JOIN scheduler_schedule ss on ss.section_id = sse.id
                    WHERE sse.course_id = $course_id)) num_pending

                FROM courses c
                WHERE id = $course_id
                HAVING num_requests;";
        $course_details = query($sql,__LINE__);
        $course_details = array_pop($course_details);

        $sql = "SELECT
                    u.id id,
                    CONCAT(u.last_name,' ' ,u.first_name) name,
                    '-' as section_id,
                    'PEND' as status
                FROM courses_requests cr
                LEFT JOIN users u on u.id = cr.user_id
                WHERE cr.course_id = 1001
                AND u.id NOT IN
                (SELECT u.id count from scheduler_sections sse
                    JOIN scheduler_schedule ss on ss.section_id = sse.id
                    WHERE sse.course_id = 1001 AND ss.user_id = u.id)
                UNION -- NICE!!!
                SELECT
                    u.id id,
                    CONCAT(u.last_name,' ' ,u.first_name) name,
                    section_id,
                    'Scheduled' as status
                FROM courses_requests cr
                LEFT JOIN users u on u.id = cr.user_id
                JOIN scheduler_schedule ss on ss.user_id = u.id
                JOIN scheduler_sections sse on sse.id = ss.section_id
                WHERE cr.course_id = 1001
                AND u.id IN
                (SELECT u.id count from scheduler_sections sse
                    JOIN scheduler_schedule ss on ss.section_id = sse.id
                    WHERE sse.course_id = 1001 AND ss.user_id = u.id)
                ORDER BY status, name;";
        $users_details = query($sql,__LINE__);

        foreach($users_details as $details) {
            $course_details['details'][] = (object) $details;
        }

        //error_log(print_r((object)$course_details,true));

        return ['success'=>true,'course'=>(object) $course_details];

    }

    // update courses num_requests and num_enrolled
    public static function updateCourseStats(){
        $sql = "select * from sched.courses";
        $courses = API::query($sql,__LINE__);
        if( ! empty($courses)) {
            foreach($courses as $course_id => $course) {
                // get num requests
                $sql = "select count(id) num_requests from requests where course_id = $course_id;";
                $courses[$course_id]['num_requests'] = API::query($sql, __LINE__);

                // get num enrolled
                $sql = "select count(*) enrolled FROM ( select count(id) count from schedule where course_id = 29601 group by student_id, section_id) count;";
                $courses[$course_id]['num_enrolled'] = API::query($sql, __LINE__);
            }
        }
        return ['success'=>true,'courses'=>$courses];
    }

    public static function getAllCourseDetails($school_id){
        $sql = "SELECT * FROM courses where school_id = 2 order by scheduler_abbr;";
        $courses = API::query($sql, __LINE__);
        if($courses){
            return ['success'=>true,'courses' => $courses];
        } else {
            return ['success'=>false, 'message'=>"No courses found for school ID $school_id."];
        }
    }

    public static function setScheduleMass($schedule_lines){
        if(!empty($schedule_lines))
        foreach($schedule_lines as $line){

        }
    }

    public static function checkSectionID(){
        $sql = "select";
    }

    public static function setScheduleLine($period_id, $term_id, $day_id, $student_id, $section_id){

    }

    public static function getStudentsByCourse($course_id){
        $sql = "select * from courses_requests where course_id = $course_id;";
        $students = API::query($sql, __LINE__);
        return $students;
    }

    public static function saveSchedulerTimeSlot($course_id,$student_id,$period_id,$term_id,$day_id){

        # We need to find a valid section, or make a new one
        # Check that a section exists for this slot
        $section_id = 0;
        $sql = "select * from scheduler_sections where
                    `term_id`   = $term_id AND
                    `period_id` = $period_id AND
                    `day_id`    = $day_id AND
                    `course_id` = $course_id";
        $sections = query($sql,__LINE__);
        if(count($sections) > 0){
            $section = array_pop($sections);
            $section_id = $section['id'];
        }

        # If no sections found, create one now
        if(empty($section_id)){
            $sql = "INSERT INTO tsc.scheduler_sections
                    (`term_id`,`period_id`,`day_id`,`course_id`)
                    VALUES
                    ($term_id,$period_id,$day_id,$course_id);";
            $section_id = save($sql,__LINE__);
            error_log("new section id SQL: " . $sql);
            error_log("new section id: " . $section_id);
        }

        # Delete existing schedule rows matching the requested timeslot
        $sql = "DELETE ss FROM scheduler_schedule ss
                LEFT JOIN scheduler_sections sse on ss.section_id = sse.id
                WHERE sse.period_id = $period_id
                and sse.term_id = $term_id
                and sse.day_id = $day_id
                and ss.user_id = $student_id;";
        save($sql,__LINE__);

        # Insert the new schedule row
        $sql = "INSERT INTO `tsc`.`scheduler_schedule`
                (`user_id`, `section_id`)
                VALUES ($student_id, $section_id);";
        $res = save($sql,__LINE__);

        if($res){
            return ['success' => true, 'section_id' => $section_id];
        } else {
            return false;
        }
    }

    public static function eraseScheduledTimeslot($section_id,$student_id){

        # Delete the scheduler_scheduler row
        $sql = "DELETE FROM scheduler_schedule WHERE section_id = $section_id AND user_id = $student_id LIMIT 1";
        error_log("Delete: " . $sql);
        delete($sql, __LINE__);

        # If the last student has been deleted from a section, delete that section
        $sql = "SELECT count(id) count from tsc.scheduler_scheduler WHERE section_id = $section_id;";
        $res = query($sql,__LINE__);
        if(!empty($res)){
            $res = array_pop($res);
            if(isset($res['count']) && $res['count'] == 0) {
                $sql = "DELETE FROM tsc.scheduler_sections WHERE id = $section_id;";
                save($sql,__LINE__);
            }
        }

        return ['success' => true, 'section_id' => $section_id];
    }

}

class GradeBooks {

    // get grading comments list
    public static function getGradingComments($content) {
        global $School;
        $district_id = $content['district_id'];
        $school_id = $School->id;
        $res = query("select
                        gc.id
                        ,gc.comment
                      from grading_comments gc
                      right join grading_comments_school gcs on gcs.comment_id = gc.id
                      where district_id = " . $district_id . "
                      and gcs.school_id = " . $school_id . "
                      order by comment", __LINE__);
        if( empty($res) ) return [];
        else return array_values($res);
    }

    //  get section weighted/unweihgted state
    public static function getSectionWeightedState($content) {
        $is_weighted = 1;
        $section_id = $content['section_id'];
        $res = query("select is_weighted from sections where id = " . $section_id . " limit 1;", __LINE__);
        if( !empty($res) ) $is_weighted = (int) $res[0]['is_weighted'];
        return $is_weighted == 1 ? true : false;
    }

    //  get section "Rounding to Float number" On/Off
    public static function getSectionRoundingStatus($content) {
        $is_rounding = 0;
        $section_id = $content['section_id'];
        $gp_id = $content['current_grading_period_id'];

        $res = query("select is_rounded
                      from section_grading_periods
                      where section_id = $section_id and grading_period_id = $gp_id
                      limit 1;", __LINE__);
        if( !empty($res) ) $is_rounding = (int) $res[0]['is_rounded'];
        return $is_rounding == 1 ? true : false;
    }

    //  save section weighted/unweighted state
    public static function setSectionWeightedState($content) {
        $section_id = $content['section_id'];
        $is_weighted = $content['is_weighted'] == true ? 1 : 0;

        query("update sections
                set is_weighted = " . $is_weighted . " where id = " . $section_id . " limit 1;", __LINE__);
        return true;
    }

    //  set section "Rounding to Float number" On/Off
    public static function setSectionRoundingStatus($content) {
        $section_id = $content['section_id'];
        $is_rounding = $content['is_rounding'] == true ? 1 : 0;

        # Find all non-closed grading period and set same rounding setting for each one
        $grading_period_ids = self::get_section_open_grading_periods($section_id);

        foreach($grading_period_ids as $gp_id) {

            # Check if exists
            $res = query("select id from section_grading_periods
                          where grading_period_id = $gp_id and section_id = $section_id limit 1", __LINE__);

            if ( !empty($res) ) {
                $id = array_pop($res)['id'];
                query("update section_grading_periods
                       set is_rounded = $is_rounding
                       where id = $id limit 1;", __LINE__);
            }
            else {
                $sql = "insert into section_grading_periods
                        (section_id, grading_period_id, is_rounded)
                        values
                        ($section_id, $gp_id, $is_rounding)";
                query($sql, __LINE__);
            }
        }
        return true;
    }

    private static function _get_earned_credit($grade_id, $grading_scale_id, $section_id) {

        # Credit earned; set to 0 initially; we will override if student has earned credit
        $credits_earned = 0;

        # Find out if the grade earns graduation credit
        if( !empty($grade_id) && !empty($grading_scale_id) ) {
            $is_grad_res = API::query("SELECT is_graduation
                                       FROM grades_scales_map
                                       WHERE grade_id = $grade_id
                                       AND grading_scale_id = $grading_scale_id", __LINE__);
        }

        if ( !empty($is_grad_res) ) {
            $is_graduation = intval($is_grad_res[0]['is_graduation']);

            # If it does earn credit, award the full number of credits possible for the section
            if( $is_graduation == 1 ) {
                $credits_earned_res = API::query("select credit_hours from sections where id = $section_id", __LINE__);
                if ( ! empty( $credits_earned_res ) ) {
                    $credits_earned = $credits_earned_res[0]['credit_hours'];
                }
            }
        }
        return $credits_earned;
    }

    private static function _set_grade_final($content) {

        $student_id = $content['student_id'];
        $section_id = $content['section_id'];
        $grading_period_id = $content['grading_period_id'];
        $grade_id = is_null($content['grade_id_to_set']) ? "NULL" : $content['grade_id_to_set'];
        $grading_scale_id = $content['grading_scale_id'];
        $is_standard = $content['is_standard']; //isset($content['is_standard']) ? 1 : 0;
        $percent = empty($content['overall_percent']) ? 0 : str_replace('%', '', $content['overall_percent']);
        $is_overridden = $content['is_overridden'];
        $overall_grade_comments = $content['overall_grade_comments'];
        $credits_earned = $content['credit_earned'];

        $_id = false;

        # Update the record in grades_final for this student, section, and grading_period
        # We do not include grading_scale_id here because if the school has since changed the grading_scale assigned
        # to this section, then we should overwrite older calculations of the final grade

        $sql = "SELECT *
                FROM grades_final
                WHERE
                    student_id = $student_id
                    AND section_id = $section_id
                    AND grading_period_id = $grading_period_id
                    AND is_standard = $is_standard
                LIMIT 1";
        $res = API::query($sql, __LINE__);
        //debug($res);

        // if no record found, then create new one
        if( empty($res) ) {

            $grade_id = (empty($grade_id) OR $grade_id == NULL OR $grade_id == "NULL") ? "0" : $grade_id;

            $ref_id = v4();

            $sql = "INSERT INTO grades_final
                    (
                        `sif_ref_id`,
                        `section_id`,
                        `student_id`,
                        `grade_id`,
                        `grading_period_id`,
                        `grading_scale_id`,
                        `overall_percent`,
                        `is_override`,
                        `is_standard`,
                        `credits_earned`,
                        `comments`
                    )
                    VALUES
                    (
                        '$ref_id',
                        $section_id,
                        $student_id,
                        $grade_id,
                        $grading_period_id,
                        $grading_scale_id,
                        $percent,
                        $is_overridden,
                        $is_standard,
                        '$credits_earned',
                        '$overall_grade_comments'
                    )";
            $_id = save($sql, __LINE__);
        }
        else {

            if($res[0]['is_override'] AND empty($content['clear_override_grade']) AND $res[0]['overall_percent'] == $percent) {
                $sql = "UPDATE grades_final
                        SET
                            overall_percent = $percent,
                            comments = '$overall_grade_comments',
                            grading_scale_id = $grading_scale_id
                        WHERE
                            student_id = $student_id
                            AND section_id = $section_id
                            AND grading_period_id = $grading_period_id
                            AND is_standard = $is_standard
                        LIMIT 1";
            }
            else {
                $update_grade = (empty($grade_id) OR $grade_id == NULL OR $grade_id == "NULL") ? "grade_id = 0," : "grade_id = $grade_id,";

                $sql = "UPDATE grades_final
                        SET
                            $update_grade
                            credits_earned = '$credits_earned',
                            is_override = $is_overridden,
                            overall_percent = $percent,
                            is_standard = $is_standard,
                            comments = '$overall_grade_comments',
                            grading_scale_id = $grading_scale_id
                        WHERE
                            student_id = $student_id
                            AND section_id = $section_id
                            AND grading_period_id = $grading_period_id
                            AND is_standard = $is_standard
                        LIMIT 1";
            }
            //p($sql);
            API::query($sql, __LINE__);
            $_id = array_pop($res)['id'];
        }

        $grade_final = query("SELECT
                                  id
                                  ,grade_id
                                  ,credits_earned
                                  ,overall_percent
                                  ,student_id
                                  ,is_override
                                  ,grading_period_id
                                  ,grading_scale_id
                                  ,comments
                              FROM grades_final WHERE id = $_id LIMIT 1", __LINE__);
        return array_pop($grade_final);
}

    private static function _get_grade_id($grading_scale_id, $percent, $is_rounding_on) {

        $percent = $is_rounding_on ? round($percent) : floor($percent);

        $res = API::query("select g.id
                           from grades g
                           join grades_scales_map gsm on gsm.grade_id = g.id
                           where gsm.grading_scale_id = $grading_scale_id
                           and gsm.min_score <= $percent
                           and gsm.is_override_only = 0
                           order by gsm.min_score desc
                           limit 1;", __LINE__);
        return !empty($res) ? $res[0]['id'] : null;
    }

    public static function get_grade_id_from_map($grading_scales, $percent, $is_rounding_on) {
        $percent = $is_rounding_on ? round($percent) : floor($percent);
        $g = array_filter($grading_scales, function ($g) use($percent, $is_rounding_on) {
            return $g['min_score'] <= $percent && $g['is_override_only'] == 0;
        });
        return !empty($g) ? current($g)['id'] : null;
    }

    public static function _get_grade_letter($grade_id) {

        if( !empty($grade_id) ) {
            $res = API::query("select g.name
                               from grades g
                               where id = {$grade_id}
                               limit 1;", __LINE__);
        }
        return !empty($res) ? $res[0]['name'] : '-';
    }

    public static function get_grade_letter_from_map($grading_scales, $grade_id) {
        return !empty($grade_id) && !empty($grading_scales[$grade_id]) ? $grading_scales[$grade_id]['name'] : '-';
    }

    public static function get_section_open_grading_periods($section_id) {
        $res = query("SELECT gp.id grading_period_id
                      FROM section_grading_periods sgp
                      JOIN grading_periods gp ON gp.id = sgp.grading_period_id
                      WHERE gp.closing_date >= DATE(NOW())
                      AND gp.date_start <= DATE(NOW())
                      AND sgp.section_id = {$section_id}
                      ORDER BY gp.date_start", __LINE__);
        $grading_period_ids = array_map(function($i) { return $i['grading_period_id']; }, $res);
        return $grading_period_ids;
    }

    public static function get_grading_periods_by_section_id($section_id) {
        $sql = "select
                    gp.id,
                    gp.date_start,
                    gp.date_end,
                    gp.closing_date,
                    DATEDIFF(date_end, DATE(NOW())) dateDiff,
                    DATEDIFF(date_start, DATE(NOW())) dateDiffstart,
                    (case
                        when DATE(NOW()) between date_start and date_end then 1
                        else 0
                    end) today_within,
                    concat(gp.name,
                            ' (',
                            DATE_FORMAT(gp.date_start, '%m/%d/%Y'),
                            ' - ',
                            DATE_FORMAT(gp.date_end, '%m/%d/%Y'),
                            ')') display,
                    concat(gp.date_start, '|', gp.date_end) value,
                    if(gp.closing_date < NOW(), 1, 0) is_closed,
                    sgp.is_rounded,
                    gp.is_final
                from
                    section_grading_periods sgp
                left join
                    grading_periods gp ON gp.id = sgp.grading_period_id
                where
                    sgp.section_id = $section_id
                order by today_within desc, dateDiff asc, dateDiffstart desc;";
        $grading_periods = query($sql, __LINE__);
        return $grading_periods;
    }

    private static function _get_section_grading_scale($section_id,$is_standard) {

        $field = $is_standard ? " sec.standards_based_id " : " sec.assignment_based_id ";

        $sql = "SELECT
                    $field as grading_scale_id
                FROM sections sec
                WHERE sec.id = {$section_id}";
        $res = query($sql,__LINE__);
        $row = array_pop($res);
        return $row['grading_scale_id'];
    }

    private static function _reset_dropped_grades_by_category($section_id, $open_grading_periods, $students) {

        //  For each open grading periods
        foreach( $open_grading_periods as $gp_id => $gp ) {

            # Find out if the requested grading_period is a final
            $sql = "SELECT id FROM grading_periods WHERE id = $gp_id AND is_final = 1";
            $res = query($sql,__LINE__);
            $is_final = empty($res) ? 0 : 1;
            //$is_final = $gp['is_final'];

            # Check if any scores should be dropped based on drop-scores-by-category rules
            # and update assignment_grades.is_drop appropriately
            # Drop-type 1 is low; 2 is high
            $sql = "SELECT id, category_id, drop_type, num_scores
                    FROM assignments_drop_categories
                    WHERE section_id = {$section_id}
                    AND grading_period_id = {$gp_id}
                    AND num_scores > 0";
            $drop_rules = query($sql,__LINE__);


            /* This is a tricky condition and not necessarily the best solution.  Final grading_periods tend to overlap
            other grading_periods.  So generally, you only want to clear dropped category grades for final grading periods
            if there is a specific rule for the final period.  Otherwise, only clear non-final periods, which tend not to
            overlap.  Still, users abuse the is_final flag, so this is a questionable approach.
            Example: T3: March 8 - June 13; Final Aug 26 - June 13.
            T3: Drop lowest in Cat1.
            Final: No drop rule.
            If we were to clear category drops for Final because there is no drop rule, we would also clear T3 drops.*/
            if( ($is_final == 0) OR ( $is_final == 1 AND !empty($drop_rules) ) ) {

                //  For each students
                foreach( $students as $student ) {
                    $student_id = isset($student['id']) ? $student['id'] : $student;

                    # First, clear category-based dropped grades since we are recalculating
                    # Drop type of 2 is category-based
                    $sql = "UPDATE assignment_grades ag
                            JOIN assignments_sections assign_sec ON assign_sec.assignment_id = ag.assignment_id
                            SET
                                ag.is_drop = 0,
                                ag.drop_type = NULL
                            WHERE ag.is_drop = 1
                            AND ag.drop_type = 2
                            AND ag.user_id = {$student_id}
                            AND assign_sec.section_id = {$section_id}
                            AND assign_sec.due_date BETWEEN
                                (SELECT date_start FROM grading_periods WHERE id = {$gp_id})
                            AND (SELECT date_end FROM grading_periods WHERE id = {$gp_id})";
                    query($sql,__LINE__);
                }
            }

            if( ! empty($drop_rules) ) {

                foreach( $drop_rules as $drop_rule_id => $drop_rule ) {

                    $sort_order = $drop_rule['drop_type'] == 1 ? " ASC " : " DESC ";

                    //  For each students
                    foreach( $students as $student ) {
                        $student_id = isset($student['id']) ? $student['id'] : $student;

                        # Get the assignment_grades records for this section that meet the criteria
                        $sql = "SELECT
                                    ag.id
                                    ,round(((ag.grade/assign_sec.points_possible) * 100 ),2) as percentage
                                FROM assignment_grades ag
                                JOIN assignments_sections assign_sec ON assign_sec.assignment_id = ag.assignment_id AND assign_sec.section_id = {$section_id}
                                WHERE assign_sec.category_id = {$drop_rule['category_id']}
                                AND ag.user_id = {$student_id}
                                AND assign_sec.due_date BETWEEN
                                    (SELECT date_start FROM grading_periods WHERE id = {$gp_id})
                                AND (SELECT date_end FROM grading_periods WHERE id = {$gp_id})
                                AND ag.grade >= 0
                                ORDER BY percentage $sort_order
                                LIMIT {$drop_rule['num_scores']}";
                        $res = query($sql,__LINE__);

                        if( !empty($res) ) {
                            $idx = implode(',', array_keys($res));
                            $sql = "UPDATE assignment_grades
                                    SET is_drop = 1, drop_type = 2
                                    WHERE id IN ($idx)";
                            query($sql, __LINE__);
                        }

                    }
                }
            }
        }

        return TRUE;
    }

    # We need a function that sets rounding by grading period appropriately
    # Scores dropped by category are handled here; scores dropped by assignment or by student should be updated separately

    public static function saveStudentFinalGrade($content) {

        //TODO: Withdrawn students should not have grades entered

        $students = $content['students']; // This should be an array of students to update
        $section_id = $content['section_id'];
        $is_standard = $content['is_standard']; //isset($content['is_standard']) ? 1 : 0;
        $update_closed_grading_period = isset($content['update_closed_period_grade']) ? 1 : 0;

        $current_grading_period_id = $content['grading_period_id']; // This is the currently-selected grading period; use this only to set overrides and comments
        $content['current_grading_period_id'] = $content['grading_period_id'];

        $is_override_request = 0; //isset($content['is_overridden']) ? 1 : 0;

        // ^AY
        # If not grading scale ID set as params, then retrieve grading scale ID for current section
        if ( !isset($content['grading_scale_id']) ) {
            $content['grading_scale_id'] = self::_get_section_grading_scale($section_id,$is_standard);
        }

        # If not grading scales Map pass by params, then retrieve for current section's grading scale ID
        if ( !isset($content['grading_scales']) ) {
            $grading_scales = API::getAllGradeScales($content);
            $content['grading_scales'] = $grading_scales;
        }
        $grading_scales = $content['grading_scales'];
        // AY$

        # @Megan
        #$content['grading_scales'] = isset($content['grading_scales']) ? $content['grading_scales'] : self::_get_section_grading_scale($section_id,$is_standard);


        $_return_data = [];
        $overall_percent = 0;


        # Add my_grades_widget parameters
        $content['my_grades_widget'] = isset($content['my_grades_widget']) ? $content['my_grades_widget'] : 0;

        # If request is coming from my_grades file, we need to set some values differently
        if( $content['my_grades_widget'] == 1) {

            // @Megan
            /*$grading_period_ids = self::get_section_open_grading_periods($section_id);
            $open_grading_periods = $grading_period_ids;
            $content['grading_periods'] = array_keys($grading_period_ids);*/

            // ^AY
            // New Grading Periods Retrieve procedure
            $grading_periods = self::get_grading_periods_by_section_id($section_id);
            $content['grading_periods'] = $grading_periods;

            if($update_closed_grading_period == 1) {
                $open_grading_periods = $grading_periods;
            }
            else {
                $open_grading_periods = array_filter($grading_periods, function ($gp) {
                    return !$gp['is_closed'];
                });
            }
            $content['open_grading_periods'] = $open_grading_periods;
            $grading_period_ids = array_keys($content['open_grading_periods']);
            // AY$

            $is_weighted = self::getSectionWeightedState($content);
            $content['grading_scale_id'] = self::_get_section_grading_scale($section_id,$is_standard);
            $grading_scale_id = $content['grading_scale_id'];
        }
        else {
            # Get all periods for this section that have started but not yet closed
            $open_grading_periods = $content['open_grading_periods'];
            $grading_period_ids = array_keys($content['open_grading_periods']); //self::get_section_open_grading_periods($section_id);

            # Find out if the section is weighted or unweighted
            $is_weighted = $content['is_weighted']; //self::getSectionWeightedState($content);

            # Get the grading_scale_id for this section and grading mode (assignment-based / standards-based)
            $grading_scale_id = $content['grading_scale_id']; //self::_get_section_grading_scale($section_id,$is_standard);
        }
        $grading_period_ids_imploded = implode(',', $grading_period_ids);

        # Find out if the current grading period has closed already
        $sql = "SELECT id FROM grading_periods WHERE id = $current_grading_period_id AND closing_date < NOW()";
        $res = query($sql,__LINE__);
        $is_closed = empty($res) ? 0 : 1;
        //$is_closed = $content['grading_periods'][$current_grading_period_id]['is_closed'];

        # Initialize empty array to hold students' grades
        $_sg = array();

        if( ! empty($students) ) {

            # If the current grading period view is closed, we are just returning some information
            if ( $is_closed AND $update_closed_grading_period == 0 ) {

                $is_rounding_on = $content['grading_periods'][$current_grading_period_id]['is_rounded'];

                if ( !$is_standard ) {

                    foreach( $students as $student_id => $student ) {

                        $student_grade_id = isset($student['grade_id']) ? $student['grade_id'] : 0;

                        $_return_data[$student_id] = [
                            'grade_id' => self::get_grade_id_from_map($grading_scales, $overall_percent, $is_rounding_on),
                            'letter' => self::get_grade_letter_from_map($grading_scales, $student_grade_id), //self::_get_grade_letter($grade_final['grade_id']),
                            'overall_percent' => isset($student['overall_percent']) ? $student['overall_percent'] : 0,
                            'overall_grade_comments' => isset($student['overall_grade_comments']) ? $student['overall_grade_comments'] : "",
                            'is_overridden' => isset($student['is_overridden']) ? $student['is_overridden'] : 0,
                            'is_rounded' => $is_rounding_on
                        ];
                    }
                }
                else {
                    foreach( $students as $student_id => $student ) {
                        $_return_data[$student_id] = [
                            'grading_period_id' => $current_grading_period_id,
                            'grade_final_id' => $student['grade_final_id'],
                            'student_id' => $student_id,
                            'grade_id' => $student['grade_id'],
                            'letter' => self::get_grade_letter_from_map($grading_scales, $student['grade_id']), //self::_get_grade_letter($grade_final['grade_id']),
                            'overall_percent' => $student['overall_percent'],
                            'overall_grade_comments' => $student['overall_grade_comments'],
                            'is_overridden' => $student['is_overridden'],
                            'is_rounded' => $is_rounding_on
                        ];
                    }
                }
            }
            # If Current Grading Period is Open
            else {

                if( !$is_standard ) {

                    # Reset dropped grades by category
                    self::_reset_dropped_grades_by_category($section_id, $open_grading_periods, $students);

                    $sql = "SELECT
                                cat.id as category_id
                                ,cat.name
                                ,sgp.is_rounded
                                ,gp.id as grading_period_id
                                ,sched.user_id
                                ,cat_gp.weight as original_weight
                                ,COUNT(CASE WHEN ag.is_drop = 0 AND ag.grade IS NOT NULL THEN 1 END) AS assignment_count
                                ,IFNULL(TRUNCATE(((SUM(CASE WHEN ag.grade IS NOT NULL AND ag.is_drop = 0 THEN (ag.grade * assign_sec.in_category_weight) ELSE 0 END) / SUM(CASE WHEN (ag.grade * assign_sec.in_category_weight) IS NOT NULL AND ag.is_drop = 0 THEN (assign_sec.points_possible * assign_sec.in_category_weight) ELSE 0 END))*100),2),0) AS average
                                ,IFNULL(TRUNCATE(SUM(CASE WHEN ag.grade IS NOT NULL AND ag.is_drop = 0 THEN (ag.grade * assign_sec.in_category_weight) ELSE 0 END),2),0) as points_earned
	                            ,IFNULL(TRUNCATE(SUM(CASE WHEN ag.grade IS NOT NULL AND ag.is_drop = 0 THEN (assign_sec.points_possible * assign_sec.in_category_weight) ELSE 0 END),2),0) AS points_possible

                            FROM assignment_categories cat
                            JOIN sections sec ON sec.id = cat.section_id
                            JOIN schedule sched ON sched.section_id = sec.id
                            JOIN section_grading_periods sgp ON sgp.section_id = sec.id
                            JOIN grading_periods gp ON gp.id = sgp.grading_period_id
                            JOIN assignment_categories_gp cat_gp ON cat_gp.category_id = cat.id AND cat_gp.grading_period_id = gp.id
                            LEFT JOIN assignments_sections assign_sec ON assign_sec.category_id = cat.id AND assign_sec.due_date BETWEEN gp.date_start AND gp.date_end
                            LEFT JOIN assignment_grades ag ON ag.assignment_id = assign_sec.assignment_id AND ag.user_id = sched.user_id

                            WHERE sched.section_id = {$section_id}
                            AND cat.is_standard_category = 0
                            AND gp.id IN ($grading_period_ids_imploded)
                            GROUP BY sched.user_id, gp.id, cat.id
                            ORDER BY sched.user_id, gp.id, cat.id";
                    $category_assignment_grades = query($sql,__LINE__);
                }
                else {
                    $sql = "SELECT
                                sg.user_id
                                ,sg.grading_period_id
                                ,sg.section_id
                                ,sec.standards_based_id
                                ,IFNULL(TRUNCATE(AVG(gsm.min_score),2),0) AS average
                            FROM sections sec
                            JOIN schedule sched ON sched.section_id = sec.id
                            JOIN section_grading_periods sgp ON sgp.section_id = sec.id
                            JOIN grading_periods gp ON gp.id = sgp.grading_period_id

                            LEFT JOIN standards_grades sg ON sg.section_id = sec.id AND sg.user_id = sched.user_id
                            LEFT JOIN grades g ON g.id = sg.grade_id
                            JOIN grades_scales_map gsm ON gsm.grade_id = g.id AND gsm.grading_scale_id = sec.standards_based_id
                            WHERE sec.id = {$section_id}
                            AND sg.grading_period_id IN ($grading_period_ids_imploded)
                            GROUP BY sched.user_id, gp.id
                            ORDER BY sched.user_id, gp.id";

                    $standard_assignment_grades = query($sql,__LINE__);
                }

                //debug($students);
                foreach( $students as $student ) {

                    $student_id = isset($student['id']) ? $student['id'] : $student;

                    $content['student_id'] = $student_id;

                    # Check if override or not from student attribute
                    if ( isset($student['is_overridden']) ) {
                        $is_override_request = $student['is_overridden'];
                        $content['is_overridden'] = $is_override_request;
                    }

                    # Check if clear override request from student attribute
                    if ( isset($student['clear_override_grade']) ) {
                        $content['clear_override_grade'] = $student['clear_override_grade'];
                    }

                    # Assignment-based grading
                    if( !$is_standard ) {

                        if( ! empty($grading_period_ids) ) {
                            foreach($grading_period_ids as $gp_id) {

                                # Get rounding status
                                if($content['my_grades_widget'] == 1) {
                                    $is_rounding_on = self::getSectionRoundingStatus($content);
                                }
                                else {
                                    $is_rounding_on = $content['grading_periods'][$gp_id]['is_rounded']; //self::getSectionRoundingStatus($content);
                                }

                                # Weighted calculation method
                                if( $is_weighted ) {

                                    $final_grade = 0;
                                    $unused_weight = 0;
                                    $used_weight = 0;

                                    # Weight the averages in each category appropriately
                                    if( ! empty($category_assignment_grades) ) {
                                        foreach( $category_assignment_grades as $key => $value ) {

                                            # Get the sum of weights of all categories that have assignments versus no assignments
                                            if( $category_assignment_grades[$key]['user_id'] == $student_id AND $category_assignment_grades[$key]['grading_period_id'] == $gp_id ) {
                                                if (  $category_assignment_grades[$key]['assignment_count'] > 0 ) {
                                                    $used_weight += $category_assignment_grades[$key]['original_weight'];
                                                }
                                                else {
                                                    $unused_weight += $category_assignment_grades[$key]['original_weight'];
                                                }
                                            }
                                        }

                                        foreach( $category_assignment_grades as $key => $value ) {

                                            # Adjust the weight for each category; if the unused_weight > 0, then we need to re-balance the used_weights proportionately
                                            if( $category_assignment_grades[$key]['user_id'] == $student_id AND $category_assignment_grades[$key]['grading_period_id'] == $gp_id ) {
                                                if( $unused_weight > 0 ) {
                                                    if( $category_assignment_grades[$key]['assignment_count'] > 0 ) {
                                                        $category_assignment_grades[$key]['original_weight'] += round( ( ($category_assignment_grades[$key]['original_weight'] / $used_weight) * $unused_weight ), 2 );
                                                    }
                                                    else {
                                                        $category_assignment_grades[$key]['original_weight'] = 0;
                                                    }
                                                }

                                                # Weight the average
                                                $weighted_average = round(($category_assignment_grades[$key]['average'] * $category_assignment_grades[$key]['original_weight'] / 100), 2);
                                                $final_grade += $weighted_average;
                                            }
                                        }
                                        $overall_percent = number_format($final_grade, 2,'.','');
                                    }
                                }

                                # If unweighted (total points method)
                                else {

                                    $total_points_earned = 0;
                                    $total_points_possible = 0;

                                    if( ! empty($category_assignment_grades) ) {
                                        foreach( $category_assignment_grades as $key => $value ) {
                                            if( $category_assignment_grades[$key]['user_id'] == $student_id AND $category_assignment_grades[$key]['grading_period_id'] == $gp_id ) {
                                                $total_points_earned += $category_assignment_grades[$key]['points_earned'];
                                                $total_points_possible += $category_assignment_grades[$key]['points_possible'];
                                            }
                                        }
                                    }

                                    # Get the final grade
                                    $overall_percent = $total_points_possible != 0 ? number_format((($total_points_earned / $total_points_possible) * 100), 2,'.','') : 0;
                                }

                                # Get the (letter) grade
                                $_sg[$student_id]['overall_percent'] = $overall_percent;

                                # If request is coming from my_grades file, we need to set some values differently
                                if($content['my_grades_widget'] == 1) {
                                    $student_id = $content['students'][0];
                                    $_sg[$student_id]['final_grade_id'] = self::_get_grade_id($grading_scale_id, $overall_percent, $is_rounding_on);
                                }
                                else {
                                    $_sg[$student_id]['final_grade_id'] = self::_get_grade_id($grading_scale_id, $overall_percent, $is_rounding_on);; //self::get_grade_id_from_map($grading_scales, $overall_percent, $is_rounding_on);
                                }

                                /*
                                 * Whatever grade was calculated, write it to grades_final for this grading_period_id
                                 * Make sure to either round or not round based on teacher's grade book setting
                                 */
                                $content['student_id'] = $student_id;
                                $content['grading_period_id'] = $gp_id;
                                $content['overall_percent'] = $_sg[$student_id]['overall_percent'];

                                # ^AY
                                #   Get grading period comment, if no comment set
                                #   This check need to do for both below cases:
                                #       1. When Current Grading Period(Requested) is not same with iterator
                                #       2. When request comes from my_grade_widget
                                # AY$

                                # @Megan
                                # if ( $current_grading_period_id != $gp_id )
                                if ( ($current_grading_period_id != $gp_id) || ($content['my_grades_widget'] == 1) ) {
                                    $res = API::query("SELECT comments FROM grades_final
                                                       WHERE
                                                           student_id = $student_id
                                                           AND section_id = $section_id
                                                           AND grading_period_id = $gp_id
                                                           AND is_standard = $is_standard
                                                       LIMIT 1;", __LINE__);
                                    $content['overall_grade_comments'] = !empty($res) ? escape($res[0]['comments']) : null;
                                }
                                else {
                                    /*$content['overall_grade_comments'] = !isset($content['overall_grade_comments'])
                                        ? null
                                        : $content['overall_grade_comments'];*/
                                    $content['overall_grade_comments'] = isset($student['overall_grade_comments'])
                                        ? escape($student['overall_grade_comments'])
                                        : null;
                                }

                                # Set final grade
                                if($is_override_request AND $gp_id == $current_grading_period_id) {
                                    $content['grade_id_to_set'] = $student['grade_id']; //$content['grade_id'];
                                    $content['is_overridden'] = 1;
                                    $content['credit_earned'] = self::_get_earned_credit($content['grade_id_to_set'], $grading_scale_id, $section_id);
                                    $grade_final = self::_set_grade_final($content);
                                }
                                else {
                                    $content['grade_id_to_set'] = $_sg[$student_id]['final_grade_id'];
                                    $content['is_overridden'] = 0;
                                    $content['credit_earned'] = self::_get_earned_credit($content['grade_id_to_set'], $grading_scale_id, $section_id);
                                    $grade_final = self::_set_grade_final($content);
                                }

                                # Prepare returned Object for current grading period only
                                if ( $current_grading_period_id == $gp_id ) {
                                    $_return_data[$student_id] = [
                                        'grading_period_id' => $gp_id,
                                        'grade_final_id' => $grade_final['id'],
                                        'student_id' => $student_id,
                                        'grade_id' => $content['grade_id_to_set'],
                                        'letter' => self::get_grade_letter_from_map($grading_scales, $content['grade_id_to_set']), //self::_get_grade_letter($content['grade_id_to_set'])
                                        'overall_percent' => $content['overall_percent'],
                                        'credit_earned' => $content['credit_earned'],
                                        'overall_grade_comments' => isset($student['overall_grade_comments']) ? $student['overall_grade_comments'] : "",//$content['overall_grade_comments'],
                                        'is_overridden' => $grade_final['is_override'],
                                        'is_rounded' => $is_rounding_on
                                    ];
                                }
                            }
                        }
                    }

                    # Standards-based grading
                    else {
                        if( ! empty($grading_period_ids) ) {
                                foreach($grading_period_ids as $gp_id) {

                                    //  update grading period ID with each iteration
                                    $content['grading_period_id'] = $gp_id;

                                    $is_rounding_on = $content['grading_periods'][$gp_id]['is_rounded'];

                                    if( ! empty($standard_assignment_grades) ) {
                                        foreach( $standard_assignment_grades as $key => $value ) {
                                            if( $standard_assignment_grades[$key]['user_id'] == $student_id AND $standard_assignment_grades[$key]['grading_period_id'] == $gp_id ) {

                                                $grading_scale_id = $standard_assignment_grades[$key]['standards_based_id'];
                                                $content['overall_percent'] = $standard_assignment_grades[$key]['average'];
                                            }
                                        }
                                    }
                                    else {
                                        $grading_scale_id = $content['grading_scale_id'];
                                        $content['overall_percent'] = $student['overall_percent'];
                                    }

                                    $_sg[$student_id]['final_grade_id'] = self::_get_grade_id($grading_scale_id, $content['overall_percent'], 0);

                                    # Get grading period comment, if no comment set
                                    if ($current_grading_period_id != $gp_id) {
                                        $res = API::query("SELECT comments FROM grades_final
                                                           WHERE
                                                               student_id = $student_id
                                                               AND section_id = $section_id
                                                               AND grading_period_id = $gp_id
                                                               AND is_standard = $is_standard
                                                           LIMIT 1;", __LINE__);
                                        $content['overall_grade_comments'] = !empty($res) ? $res[0]['comments'] : null;
                                    }
                                    else {
                                        $content['overall_grade_comments'] = !isset($content['overall_grade_comments'])
                                            ? null
                                            : $content['overall_grade_comments'];
                                    }

                                    # Set final grade
                                    if($is_override_request AND $gp_id == $current_grading_period_id) {
                                        $content['grade_id_to_set'] = $student['grade_id']; //$content['grade_id'];
                                        $content['is_overridden'] = 1;
                                        $content['credit_earned'] = self::_get_earned_credit($content['grade_id_to_set'], $grading_scale_id, $section_id);
                                        //debug($content);
                                        $grade_final = self::_set_grade_final($content);
                                        //debug($grade_final);
                                    }
                                    else {
                                        $content['grade_id_to_set'] = $_sg[$student_id]['final_grade_id'];
                                        $content['is_overridden'] = 0;
                                        $content['credit_earned'] = self::_get_earned_credit($content['grade_id_to_set'], $grading_scale_id, $section_id);
                                        $grade_final = self::_set_grade_final($content);
                                    }

                                    # Prepare returned Object for current grading period only
                                    if ( $current_grading_period_id == $gp_id ) {
                                        $_return_data[$student_id] = [
                                            'grading_period_id' => $gp_id,
                                            'grade_final_id' => $grade_final['id'],
                                            'student_id' => $student_id,
                                            'grade_id' => $content['grade_id_to_set'],
                                            'letter' => self::get_grade_letter_from_map($grading_scales, $content['grade_id_to_set']), //self::_get_grade_letter($content['grade_id_to_set']),
                                            'overall_percent' => $content['overall_percent'],
                                            'credit_earned' => $content['credit_earned'],
                                            'overall_grade_comments' => $content['overall_grade_comments'],
                                            'is_overridden' => $grade_final['is_override'],
                                            'is_rounded' => $is_rounding_on
                                        ];
                                    }
                                }
                            }
                    }
                }
            }
        }

        return $_return_data;
    }

    public static function saveManyStudentsFinalGrade($contents) {
        $res = self::saveStudentFinalGrade($contents);
        return $res;
    }

    //  get grading periods for a section
    public static function getGradingPeriods($section_id) {
        $sql = "select
                    gp.id,
                    gp.date_start,
                    gp.date_end,
                    gp.closing_date,
                    DATEDIFF(date_end, DATE(NOW())) dateDiff,
                    (case
                        when DATE(NOW()) between date_start and date_end then 1
                        else 0
                    end) today_within,
                    concat(gp.name,
                            ' (',
                            DATE_FORMAT(gp.date_start, '%m/%d/%Y'),
                            ' - ',
                            DATE_FORMAT(gp.date_end, '%m/%d/%Y'),
                            ')') display,
                    concat(gp.date_start, '|', gp.date_end) value
                from
                    section_grading_periods sgp
                left join
                    grading_periods gp ON gp.id = sgp.grading_period_id
                where
                    sgp.section_id = $section_id
                order by today_within desc, dateDiff desc;";
        $grading_periods = API::query($sql, __LINE__);
        return $grading_periods;
    }

    //  get color scheme for different range of score
    public static function getColors($section_id) {
        $sql = "select * from gradebook_colors where section_id = $section_id;";
        $colors = API::query($sql, __LINE__);
        $grade_colors = [];
        foreach($colors as $color) {
            $grade_colors[] = [
                'id' => $color['id'],
                'title' => $color['title'],
                'section_id' => $color['section_id'],
                'min_score' => $color['min_score'],
                'max_score' => $color['max_score'],
                'color' => '#'. $color['color']
            ];
        }
        return $grade_colors;
    }

    //  update min_score and value from grade scale model
    //  in grade book page, only teachers' are allowed to do this edit
    public static function updateGradeScale($content) {

        $field = $content['type'];
        $id = $content['id'];
        $value = $content['value'];

        $sql = "update `grades_sections` set " . $field . "=" . $value . " where id=" . $id . " limit 1;";
        query($sql, __LINE__);
        return ['success' => true];
    }

    public static function getGradesList($section_id, $preserve_index = false) {

        //  get assignment_based_id for corresponding section_id
        $sql = "select id, assignment_based_id from sections where id = " . $section_id;
        $res = API::query($sql, __LINE__);
        if (empty($res)) {
            return ['success' => false,
                'message' => 'No grading scale selected.  Please contact your system administrator.'];
        }
        $res = array_values($res);
        $assignment_based_id = $res[0]['assignment_based_id'];
        if (!$assignment_based_id) {
            return ['success' => false,
                'message' => 'No grading scale selected.  Please contact your system administrator.'];
        }

        //  get all grades that not belong to `grades_sections` table
        //  and entry to `grades_sections` table and then retrieve
        //  grades list (JSON) from that table and use

        $sql = "select
                    sec.assignment_based_id assignment_based_id,
                    g.id grade_id,
                    gsm.is_gpa,
                    gsm.is_graduation,
                    gsm.value,
                    gsm.min_score,
                    g.name,
                    g.description
                from sections sec
                left join grades_scales_map gsm ON gsm.grading_scale_id = sec.assignment_based_id
                left join grades g ON g.id = gsm.grade_id
                where sec.id = $section_id
                and g.id not in (select grade_id
                                 from grades_sections
                                 where section_id = $section_id)
                order by min_score desc;";
        $new_grades = API::query($sql, __LINE__);

        if (!empty($new_grades)) {
            $new_grades = array_values($new_grades);
            $row = count($new_grades);

            $new_entries = "insert into `grades_sections`
                                (section_id, grade_id, name, min_score, value, description)
                           values ";
            foreach ($new_grades as $key => $grade) {
                $grade_id = empty($grade['grade_id']) ? 0 : $grade['grade_id'];
                $min_score = empty($grade['min_score']) ? 0 : $grade['min_score'];
                $value = empty($grade['value']) ? 0 : $grade['value'];
                $description = $grade['description'];
                $name = $grade['name'];

                $new_entries .= "($section_id, $grade_id, '{$name}', $min_score, $value, '{$description}')";
                $new_entries .= ($key < $row - 1) ? ' , ' : '';
            }
            $new_entries .= ";";
            API::query($new_entries, __LINE__);
        }

        //  get grades list (JSON) from `grades_sections` table
        $sql = "select
                    g.id, gs.grade_id, g.name letter, gs.min_score minScore, gs.value value
                from
                    grades_sections gs
                left join
                    grades g on g.id = gs.grade_id
                where
                    section_id = $section_id
                        and gs.grade_id in (select
                                            grade_id
                                        from
                                            grades_scales_map
                                        where
                                            grading_scale_id = $assignment_based_id
                                        )
                order by gs.min_score desc;";
        $res = query($sql, __LINE__);
        $grades = array_values($res);

        //  Keep Array Index as grades.id
        if ( $preserve_index ) $grades = $res;

        //  Check IS Letter Grade Show to Overall grade column
        $res = query("select is_show_letters from sections where id = " . $section_id, __LINE__);
        $is_show_letters = $res[0]['is_show_letters'];

        return ['success' => true, 'grades' => $grades, 'is_show_letters' => $is_show_letters];
    }

    public static function setGrade($user_id, $assignment_id, $grade, $comment, $date_submitted, $original_grade, $is_drop, $drop_type) {
        global $User;

        $original_grade = empty($original_grade) ? $grade : $original_grade;
        $_id = false;

        // check if not exists then create new record
        $sql = "select id from assignment_grades where assignment_id = $assignment_id and user_id = $user_id limit 1;";
        $res = API::query($sql, __LINE__);
        if( empty($res) ) {
            $sql = "INSERT INTO `assignment_grades`
                    (`user_id`,`assignment_id`,`grade`,`created_by`,`teacher_id`,`notes`,`original_grade`)
                    VALUES
                    ($user_id,$assignment_id,$grade,$User->id,$User->id,$comment,$original_grade);";
            $_id = save($sql, __LINE__);
            return $_id;
        }
        $grade = is_null($grade) ? null : $grade;
        $sql = "update
                    assignment_grades
                set";
        $sql .= " grade = {$grade}";
        $sql .= ",is_drop= $is_drop";
        $sql .= ",drop_type= " . (empty($drop_type) ? 'NULL' : $drop_type);
        $sql .= ",original_grade = $original_grade";
        if(!empty($date_submitted)) $sql .= ",date_submitted = '{$date_submitted}'";
        if(!empty($comment)) $sql .= ",notes = {$comment}";
        $sql .= "
                where
                    assignment_id = $assignment_id
                    and user_id = $user_id
                limit 1;";
        API::query($sql, __LINE__);
        $_id = array_pop($res)['id'];
        return $_id;
    }

    public static function get_grades($student_id, $section_id, $assignment_idx = null,
                                      $date_start = null, $date_end = null, $inc_gradebook) {

        $sql = "select
					assign_sec.assignment_id
                from assignments_sections assign_sec
				left join assignment_categories ac on ac.id = assign_sec.category_id
				where assign_sec.section_id = $section_id
                and assign_sec.inc_gradebook = $inc_gradebook";

        //  if date_start and date_end not null
        //  then find assignments within that date range
        if( !empty($date_start) && !empty($date_end) ) {
            $sql .= "
                    and DATE(assign_sec.due_date) between '{$date_start}' and '{$date_end}'";
        }
        $sql .= "
                order by assign_sec.due_date asc";

        $res = API::query($sql, __LINE__);
        if( !empty($res) ) {
            $assignment_idx = implode(',', do_extract($res, 'id'));
        }

        $sql = "select
                    g.assignment_id,
                    g.grade,
                    g.original_grade,
                    g.notes as comment,
                    g.is_drop,
                    g.drop_type,
                    if(g.date_submitted = NULL, NULL, DATE_FORMAT(g.date_submitted,'%Y-%m-%d')) date_submitted
                from assignment_grades g
                where g.user_id = $student_id";
        if( !empty($assignment_idx) ) $sql .= " and g.assignment_id in ($assignment_idx)";
        $sql .= ";";

        $grades = API::query($sql, __LINE__);
        $assignments_grades = [];
        foreach ($grades as $grade) {
            $assignments_grades[$grade['assignment_id']] = array(
                'grade' => $grade['grade'],
                'original_grade' => $grade['original_grade'],
                'comment' => $grade['comment'],
                'date_submitted' => $grade['date_submitted'],
                'is_drop' => $grade['is_drop'],
                'drop_type' => $grade['drop_type']
            );
        }
        $grades_list = [];
        $Assignments = new Assignments();
        $assignments = $Assignments->get_assignments($section_id, $assignment_idx, $date_start, $date_end);

        if ( !empty($assignments) ) {
            foreach ($assignments as $assignment) {
                if (array_key_exists($assignment['id'], $assignments_grades)) {
                    $grades_list[] = $assignments_grades[$assignment['id']];
                } else {
                    $grades_list[] = array(
                        'grade' => null,
                        'original_grade' => null,
                        'comment' => null,
                        'date_submitted' => null,
                        'is_drop' => null,
                        'drop_type' => null
                    );
                }
            }
        }
        if ($grades_list)
            return $grades_list;
        return false;
    }

    /*public static function set_section_grade($user_id, $section_id, $grade_id) {

        $sql = "delete from `sections` where user_id = $user_id and section_id = $section_id LIMIT 1";
        save($sql, __LINE__);

        if (!empty($grade_id) AND !empty($section_id) AND !empty($user_id)) {
            $sql = "insert into section (user_id,section_id,grade_id) VALUES ($user_id,$section_id,$grade_id);";
            save($sql, __LINE__);
        } else {
            return false;
        }
        return true;
    }*/

}

class Assignments {

    //  keep record of score dropped for a assignment (Drop - by assignment)
    public static function toggleDropByAssignment($params) {
        $assignment_id = $params['assignment_id'];
        $section_id = $params['section_id'];
        $isDrop = $params['is_drop'];
        $sql = "update assignments_sections set drop_score_by_assignment = $isDrop where id = $assignment_id and section_id = $section_id";
        API::query($sql, __LINE__);
        return ['success' => true];
    }

    public static function getDropScoresBySection($params) {
        $section_id = $params['section_id'];
        $sql = "select
                    adc.id assign_cat_drop_id,
                    ac.name category_name,
                    IF(adc.drop_type = 2, 'Highest', 'Lowest') drop_type_name,
                    adc.num_scores,
                    concat(gp.name,
                            ' (',
                            DATE_FORMAT(gp.date_start, '%m/%d/%Y'),
                            ' - ',
                            DATE_FORMAT(gp.date_end, '%m/%d/%Y'),
                            ')') display
                from assignments_drop_categories adc
                left join grading_periods gp on gp.id = adc.grading_period_id
                left join assignment_categories ac on ac.id = adc.category_id
                where adc.section_id = $section_id and adc.num_scores > 0
                order by display;";
        $categories = API::query($sql, __LINE__);
        return $categories;
    }

    public static function deleteDropScoreRecord($params) {
        $assign_cat_drop_id = $params['assign_cat_drop_id'];
        query("update assignments_drop_categories
              set drop_type = 0, num_scores = 0
              where id = $assign_cat_drop_id
              limit 1", __LINE__);
        return true;
    }

    //  get all assignment categories for a sections
    public static function getAllCategories($params) {
        $section_id = $params['section_id'];
        $sql = "select
                    ac.id
                    ,ac.name
                    ,ac.weight
                    ,adc.drop_type
                    ,adc.num_scores
                from assignment_categories ac
                left join assignments_drop_categories adc on adc.category_id = ac.id
                where ac.section_id = $section_id
                and ac.is_standard_category = 0
                order by adc.drop_type asc;";
        $categories = API::query($sql, __LINE__);

        $category_idx = array_unique(do_extract($categories, 'id'));

        $result = [];
        foreach($category_idx as $index => $cat_id) {
            $res = do_extract($categories, '[id='. $cat_id .']', true);
            $options = [];
            $cat_name = '';
            $cat_weight = 0;
            foreach($res as $category) {
                $cat_name = $category['name'];
                $cat_weight = $category['weight'];

                $drop_type = $category['drop_type'];
                $options[] = [
                    'drop_type' => $drop_type, // 1 = Lowest, 2 = Highest
                    'drop_type_text' => empty($drop_type) ? null : ($drop_type == 1 ? 'Lowest' : 'Highest'),
                    'score' => empty($category['num_scores']) ? 0 : $category['num_scores']
                ];

                /*if( empty($category['drop_type']) ) {
                    $options = [
                        [
                            'drop_type' => 1,
                            'drop_type_text' => 'Lowest',
                            'score' => 0
                        ],
                        [
                            'drop_type' => 2,
                            'drop_type_text' => 'Highest',
                            'score' => 0
                        ]
                    ];
                } else {
                    $drop_type = $category['drop_type'];
                    $options[] = [
                        'drop_type' => $drop_type, // 1 = Lowest, 2 = Highest
                        'drop_type_text' => $drop_type == 1 ? 'Lowest' : 'Highest',
                        'score' => empty($category['num_scores']) ? 0 : $category['num_scores']
                    ];
                }*/
            }
            $result[$index] = [
                'id' => $cat_id,
                'name' => $cat_name,
                'weight' => $cat_weight,
                'options' => $options
            ];
        }
        return $result;
    }

    public static function get_assignments($section_id, $is_gradebook = false, $date_start = null, $date_end = null) {
        global $User;

        $add_sql = "";

        if ($is_gradebook) {
            $add_sql .= " AND assign_sec.inc_gradebook = 1 ";
        }

        $sql = "SELECT
					assign.id,
                    assign.name,
                    assign_sec.due_date,
                    assign_sec.inc_gradebook graded,
                    assign_sec.points_possible points,
                    DATE_FORMAT(assign_sec.due_date, '%Y-%m-%d') due_date,
                    DATE_FORMAT(assign_sec.due_date, '%b %d') due_date_viewable,
                    DATE_FORMAT(assign_sec.due_date, '%d %b, %Y') due_date_readable,
                    ac.weight,
                    ac.id categoryId,
                    ac.name categoryName,
                    ac.dock,
                    assign_sec.drop_score_by_assignment
				FROM assignments assign
				JOIN assignments_sections assign_sec ON assign_sec.assignment_id = assign.id
				JOIN assignment_categories ac ON ac.id = assign_sec.category_id
				WHERE assign_sec.section_id = {$section_id}";
        $sql .= $add_sql;
        if( !empty($date_start) && !empty($date_end) ) {
            $sql .= "AND (DATE(assign_sec.due_date) BETWEEN '$date_start' AND '$date_end')";
        }

        $sql .= " ORDER BY assign_sec.due_date DESC; ";
        $assignments = API::query($sql, __LINE__);

        if( !empty($assignments) ) {
            foreach($assignments as $assignment_id => $assignment) {
                $assignments[$assignment_id]['name'] = utf8ize($assignment['name']);
            }
        }

        return $assignments;
    }
}

/**
 *  `Assessment` class definition 
 *  acting as a interface for all assessment builder operations
 */
class Assessment {

    // mapping over answer type and answer format
    public static $format = ['multi-choice' => 1, 'free-response' => 2];
    public static $type = ['text' => 1, 'image' => 2, 'audio' => 3, 'math' => 4, 'video' => 5, 'visual' => 6, 'flashcard' => 7];
    public static $setType = ['standard' => 1, 'visual' => 2, 'flashcard' => 3];

    //  search question for assessment builder
    public static function searchQuestionForBuilder($form_data) {

        $results = [];

        $keyword = $form_data['keywords'];
        $question_set_type = self::$setType[$form_data['question_set_type']];

        $question_types = array_map(function($i) {
                    return $i['value'];
                }, array_filter($form_data['question_types'], function($i) {
                            return $i['is_checked'];
                        }));

        //  check that question type has 'Flashcard'
        $has_flashcard = in_array(7, $question_types);

        $question_types = implode(',', $question_types);

        $answer_types = array_map(function($i) {
                    return $i['value'];
                }, array_filter($form_data['answer_types'], function($i) {
                            return $i['is_checked'];
                        }));
        $answer_types = implode(',', $answer_types);

        $standards = do_extract($form_data['standards'], 'id');
        $standards = implode(',', $standards);

        //  if search item contain flashcard, then search for Content Set
        //  and return Content Set with name and description
        if($has_flashcard){
            $sql = "select
                        id, name, description
                    from
                        datasets
                    ";
            //  prepare query conditions
            if (!empty($keyword) || !empty($standards) || !empty($question_types))
                $sql .= "where ";
            if (!empty($keyword)) {
                $sql .= "name like '%$keyword%' or description like '%$keyword%'";
            }
            if (!empty($standards)) {
                if (!empty($keyword)) $sql .= " and";
                $sql .= " standard_id in ($standards)";
            }
            $sql .= "order by
                        date_create desc;";
            $res = API::query($sql, __LINE__);
            foreach ($res as $data) {
                $results[] = [
                    'id' => $data['id'],
                    'data' => $data['description'] ,
                    'name' => $data['name'],
                    'question_type' => 7,
                    'answer_type' => 1,
                    'special_text' => '',
                    'is_checked' => false
                ];
            }
            return $results;
        }

        //  search question sets for context/ instruction
        if (!empty($keyword)) {
            $sql1 = "select
                        distinct aq.id, aq.data, aq.question_type, aq.special_text, asa.answer_type
                    from
                        assessments_questions_sets aqs
                    left join
                        assessments_questions aq on aq.question_set_id = aqs.id
                    left join
                        assessments_answers asa on asa.question_id = aq.id
                    where
                        aqs.type = $question_set_type
                        and aqs.context like '%$keyword%'
                        or aqs.instructions like '%$keyword%';";

            $res = API::query($sql1, __LINE__);
            if (!empty($res)) {
                foreach ($res as $data) {
                    $results[] = [
                        'id' => $data['id'],
                        'data' => $data['question_type'] == 7 ? json_decode($data['data']) : $data['data'],
                        'question_type' => $data['question_type'],
                        'answer_type' => $data['answer_type'],
                        'special_text' => $data['special_text'],
                        'is_checked' => false
                    ];
                }
            }
        }

        //  search question based on question types and keyword
        $sql2 = "select
                    distinct aq.id, aq.data, aq.question_type, aq.special_text, asa.answer_type
                from
                    assessments_questions aq
                        left join
                    assessments_questions_sets aqs ON aqs.id = aq.question_set_id
                        inner join
                    assessments_answers asa ON asa.question_id = aq.id
                ";
        //  prepare query conditions
        if (!empty($keyword) || !empty($standards) || !empty($question_types))
            $sql2 .= "where
                        aqs.type = $question_set_type
                        ";
        if (!empty($keyword)) {
            $sql2 .= "and aq.data like '%$keyword%'";
        }
        if (!empty($standards)) {
            if (!empty($keyword))
                $sql2 .= " and";
            $sql2 .= " aq.standard_id in ($standards)";
        }
        if (!empty($question_types)) {
            if (!empty($standards) || !empty($keyword))
                $sql2 .= " and";
            $sql2 .= " aq.question_type in ($question_types)";
        }
        //  order
        $sql2 .= " order by
                    aq.question_type asc;";
        $res = API::query($sql2, __LINE__);
        if (!empty($res)) {
            foreach ($res as $data) {
                $results[] = [
                    'id' => $data['id'],
                    'data' => $data['question_type'] == 7 ? json_decode($data['data']) : $data['data'],
                    'question_type' => $data['question_type'],
                    'answer_type' => $data['answer_type'],
                    'special_text' => $data['special_text'],
                    'is_checked' => false
                ];
            }
        }

        //  search answer based on answer type and format
        $sql3 = "select
                    distinct aq.id, aq.data, aq.question_type, aq.special_text, asa.answer_type
                from
                    assessments_answers asa
                left join
                    assessments_questions aq on aq.id = asa.question_id
                left join
                    assessments_questions_sets aqs ON aqs.id = aq.question_set_id
                ";

        //  prepare query conditions
        if (!empty($keyword) || !empty($answer_types) || !empty($question_types))
            $sql3 .= "where
                        aqs.type = $question_set_type
                      ";
        if (!empty($keyword)) {
            $sql3 .= "and asa.data like '%$keyword%'";
        }
        if (!empty($answer_types)) {
            if (!empty($keyword))
                $sql3 .= " and";
            $sql3 .= " asa.answer_type in ($answer_types)";
        }
        if (!empty($question_types)) {
            if (!empty($answer_types) || !empty($keyword))
                $sql3 .= " and";
            $sql3 .= " aq.question_type in ($question_types)";
        }
        // order
        $sql3 .= " order by
                    aq.question_type asc;";

        $res = API::query($sql3, __LINE__);
        if (!empty($res)) {
            foreach ($res as $data) {
                $results[] = [
                    'id' => $data['id'],
                    'data' => $data['question_type'] == 7 ? json_decode($data['data']) : $data['data'],
                    'question_type' => $data['question_type'],
                    'answer_type' => $data['answer_type'],
                    'special_text' => $data['special_text'],
                    'is_checked' => false
                ];
            }
        }
        $results = array_values(array_combine(array_map(function ($i) {
                                    return $i['id'];
                                }, $results), $results));
        return $results;
    }

    //  get all selected question after search in assessment builder
    public static function importSelectedQuestions($content) {

        $question_sets = [];

        $question_idx = $content['question_idx'];
        $question_set_type = $content['question_set_type'];

        $question_idx = implode(',', $question_idx);

        $sql = "select question_set_id from assessments_questions where id in ($question_idx);";
        $res = API::query($sql, __LINE__);
        $question_set_idx = do_extract($res, 'question_set_id');
        $question_set_idx = implode(',', array_unique($question_set_idx));


        // get question sets
        $sql = "select * from `tsc`.`assessments_questions_sets` where `id` in (" . $question_set_idx . ") order by sort_order asc;";
        $question_sets = API::query($sql, __LINE__);

        $assessment_view_model['questionSets'] = [];
        $result = self::_prepareQuestionSet($question_sets, $assessment_view_model, true);
        return $result;
    }

    //  get Earned Point for an Assessment by a student
    public static function earnedPoints($student_id, $assessment_idx) {
        $json = [];
        if (empty($assessment_idx)) return $json;

        foreach ($assessment_idx as $assessment_id) {
            if ($assessment_id == 0) {
                $json[] = ['assessmentId' => $assessment_id,
                    'assessment_earned_points' => 0,
                    'assessment_total_points' => 0];
            } else {
                $sql = "select
                            asa.assessment_id,
                            sum(asa.points_earned) assessment_earned_points,
                            (select
                                sum(points) total_point
                            from
                                assessments_questions aq
                            where
                                question_set_id in (select
                                        id
                                    from
                                        assessments_questions_sets
                                    where
                                        assessment_id = $assessment_id)) assessment_total_points
                        from
                            assessments_students_answers asa
                        where
                            asa.assessment_id = $assessment_id
                            and asa.user_id = $student_id;";

                $res = API::query($sql, __LINE__);

                $point_earned = empty($res[0]['assessment_earned_points']) ? 0 : $res[0]['assessment_earned_points'];
                $total = empty($res[0]['assessment_total_points']) ? 0 : $res[0]['assessment_total_points'];

                $json[] = ['assessmentId' => $assessment_id,
                    'assessment_earned_points' => $point_earned,
                    'assessment_total_points' => $total];
            }
        }
        return $json;
    }

    //  private method to prepare question set
    private static function _prepareQuestionSet($question_sets, $assessment_view_model, $import = false) {
        if (!empty($question_sets)) {

            $question_set_idx = do_extract($question_sets, 'id');

            // get questions
            $sql = "select aq.*, ss.identifier as ss_identifier, ss.standard as ss_standard from `tsc`.`assessments_questions` aq left join standards_standards ss on ss.id = aq.standard_id where `question_set_id` in (" . implode(',', $question_set_idx) . ") order by sort_order asc;";
            $all_questions = query($sql, __LINE__);

            if (!empty($all_questions)) {
                $question_idx = do_extract($all_questions, 'id');

                // get answers
                $sql = "select * from `tsc`.`assessments_answers` where `question_id` in (" . implode(',', $question_idx) . ") order by sort_order asc;";
                $all_answers = query($sql, __LINE__);
            }


            $f = array_flip(self::$format);
            $t = array_flip(self::$type);
            $qset_type = array_flip(self::$setType);
            $_index = $import ? 1 : 0;

            foreach ($question_sets as $question_set) {
                $qsid = $question_set['id'];
                $qset = ['id' => $import ? 0 : $qsid,
                    '_type' => $t[$question_set['context_type']],
                    'content' => $question_set['context'],
                    'context_type' => $t[$question_set['context_type']],
                    'heading' => $question_set['heading'],
                    'is_heading_visible' => !empty($question_set['heading']) ? true : false,
                    'instruction' => $question_set['instructions'],
                    'order' => $question_set['sort_order'],
                    'index_at' => ++$_index,
                    'type' => $qset_type[$question_set['type']],
                    'flashcard_id' => $question_set['flashcard_id'],
                    'visual_data' => []];

                if ($question_set['type'] == 2) {
                    $sql = 'select * from visual_identifications where id = ' . $question_set['visual_identification_id'];
                    $res = query($sql, __LINE__);
                    if (!empty($res)) {
                        $res = array_values($res);
                        $vdata['id'] = $res[0]['id'];
                        $vdata['img'] = $res[0]['img_path'];
                        $vdata['selections'] = json_decode($res[0]['coords']);
                    }
                    $qset['visual_data'] = $vdata;
                }

                if (!empty($all_questions)) {
                    $questions = do_extract($all_questions, '[question_set_id=' . $qsid . ']');

                    if (!empty($questions)) {
                        foreach ($questions as $key => $question) {
                            $ques = ['id' => $import ? 0 : $question['id'],
                                'order' => $question['sort_order'],
                                'question_no' => $import ? ($key+1) : $question['sort_order'],
                                'question_type' => $t[$question['question_type']],
                                '_type' => $t[$question['question_type']],
                                'content' => $question_set['type'] == 3 ? json_decode($question['data']) : $question['data'],
                                'special_text' => $question['special_text'],
                                'standard_id' => $question['standard_id'],
                                'is_hint' => $question['is_hint'],
                                'hint' => $question['hint'],
                                'hint_vis' => empty($question['hint']),
                                'ss_identifier' => $question['ss_identifier'],
                                'ss_standard' => utf8_encode($question['ss_standard'])];

                            $answers = do_extract($all_answers, '[question_id=' . $question['id'] . ']');
                            $ans = [];
                            if (!empty($answers)) {
                                $answers = array_values($answers);
                                $ans = [
                                        'answer_type' => $t[$answers[0]['answer_format']],
                                        '_type'  => $t[$answers[0]['answer_format']],
                                        'answer_format' => $f[$answers[0]['answer_type']],
                                        '_format' => $f[$answers[0]['answer_type']]
                                    ];
                                foreach ($answers as $answer) {
                                    $ans['options'][] = ['id' => $import ? 0 : $answer['id'],
                                        'uuid' => $answer['uuid'],
                                        'is_guide' => $answer['is_guide'],
                                        'guide' => $answer['guide'],
                                        'is_correct' => $answer['is_correct'],
                                        'content' => $answer['data'],
                                        'order' => $answer['sort_order']];
                                }
                            }


                            // insert answer block to question
                            $ques['answer'] = $ans;

                            // insert question block to question set
                            $qset['questions'][] = $ques;
                        }
                    }
                }
                else
                    $qset['questions'][] = [];

                // insert question set to viewModel
                $assessment_view_model['questionSets'][] = $qset;
            }
        }

        return $assessment_view_model;
    }

    //  method to load `AssessmentViewModel`
    //  if a `assessment_id` passed via url
    //  READ ACTION
    //
    //  @return:
    //          A assessment JSON
    public static function getAssessment($assessment_id) {
        global $User;
        $created_by = $User->id;

        // get assessment using `assessment_id`
        $sql = "select id, name from `tsc`.`assessments` where `id` = $assessment_id /*and created_by = $created_by */order by `id` asc limit 1";
        $assessment = query($sql, __LINE__);

        // check if no assessment exists
        if (empty($assessment))
            return false;

        $assessment = array_pop($assessment);
        if (!empty($assessment)) {
            $assessment_view_model = ['id' => $assessment['id'], 'main_heading' => $assessment['name']];

            // get question sets
            $sql = "select * from `tsc`.`assessments_questions_sets` where `assessment_id` = " . $assessment['id'] . " order by sort_order asc;";
            $question_sets = query($sql, __LINE__);

            // Preparing view Model Object
            $assessment_view_model['questionSets'] = [];
            $result = self::_prepareQuestionSet($question_sets, $assessment_view_model);
            $result['error'] = false;

            return $result;
        }
        else
            return false;
    }

    //  method to save `AssessmentsViewModel` Submit via AJAX
    //  after parsing the ViewModel JSON will save to db
    //  INSERT/ UPDATE ACTION
    public static function setAssessment($content) {

        global $User, $School;

        $created_by = $User->id;
        $school_id = $School->id;

        // data
        $data = $content;

        // save main assessment
        $main_heading = escape($data['main_heading']);

        // update / insert assessment
        $assessment_id = $data['id'];
        if ($assessment_id != 0) {
            $sql = "UPDATE 
                        `tsc`.`assessments` 
                    SET 
                        `name` = '{$main_heading}' 
                    WHERE 
                        `id` = $assessment_id 
                    LIMIT 1;";
            query($sql, __LINE__);
        } else {
            $sql = "INSERT INTO `tsc`.`assessments`
            (`school_id`,
            `created_by`,
            `name`)
            VALUES
            ($school_id,
            $created_by,
            '{$main_heading}');";

            $assessment_id = save($sql, __LINE__);

            // update the view Model assessment `id`
            $data['id'] = $assessment_id;
        }

        // update / insert assessment question set
        $sort_order = 1;
        foreach ($data['questionSets'] as $_qsid => $question_set) {

            $sort_order++;
            $question_set_type = $question_set['type'];
            $qset_type = self::$setType[$question_set_type];
            $instruction = escape(@$question_set['instruction']);
            $context = escape(@$question_set['content']);
            $_ctype = escape(@$question_set['context_type']);
            $context_type = self::$type[$_ctype];
            $heading = escape(@$question_set['heading']);
            $sort_order = escape(@$question_set['order']);
            $flashcard_id = escape(@$question_set['flashcard_id']);
            $is_heading = strlen($heading) ? 1 : 0;

            $visual_id = 0;

            //  Save visual info
            if ($question_set_type == 'visual') {
                $visual_data = $question_set['visual_data'];
                $visual_id = $visual_data['id'];
                $coords = escape(json_encode($visual_data['selections']));
                $img = escape($visual_data['img']);

                if ($visual_id != 0) {
                    $sql = "UPDATE 
                                `tsc`.`visual_identifications`
                            SET
                                `img_path` = '{$img}',
                                `coords` = '{$coords}'
                            WHERE
                                `id` = $visual_id
                            LIMIT 1;";
                    query($sql, __LINE__);
                } else {
                    $sql = "INSERT INTO 
                            `tsc`.`visual_identifications`
                        SET
                            `img_path` = '{$img}',
                            `coords` = '{$coords}',
                            `created_by` = '{$created_by}'
                        ;";

                    $visual_id = save($sql, __LINE__);
                }
            }

            $question_set_id = $question_set['id'];
            if ($question_set_id != 0) {
                $sql = "UPDATE 
                            `tsc`.`assessments_questions_sets`
                        SET
                            `is_heading` = '{$is_heading}',
                            `heading` = '{$heading}',
                            `context` = '{$context}',
                            `context_type` = '{$context_type}',
                            `sort_order` = '{$sort_order}',
                            `type` = '{$qset_type}',
                            `flashcard_id` = '{$flashcard_id}',
                            `instructions` = '{$instruction}'
                        WHERE
                            `id` = $question_set_id
                        LIMIT 1;";
                query($sql, __LINE__);
            } else {
                $sql = "INSERT INTO 
                            `tsc`.`assessments_questions_sets`
                        SET
                            `school_id` = '{$school_id}',
                            `created_by` = '{$created_by}',
                            `date_created` = NOW(),
                            `is_heading` = '{$is_heading}',
                            `heading` = '{$heading}',
                            `assessment_id` = '{$assessment_id}',
                            `context` = '{$context}',
                            `context_type` = '{$context_type}',
                            `instructions` = '{$instruction}',
                            `type` = '{$qset_type}',
                            `visual_identification_id` = '{$visual_id}',
                            `flashcard_id` = '{$flashcard_id}',
                            `sort_order` = '{$sort_order}'
                        ;";

                $question_set_id = save($sql, __LINE__);

                // update `id` of each new question set
                $data['questionSets'][$_qsid]['id'] = $question_set_id;
            }

            // update / insert assessment questions for each questoin set
            foreach ($question_set['questions'] as $_qid => $question) {
                if (is_array($question['content'])) {
                    $question_content = escape(json_encode($question['content']));
                }
                else
                    $question_content = escape(@$question['content']);
                $special_text = escape(@$question['special_text']);

                if ($question_set_type == 'visual') {
                    $question_type = 6;
                } else if ($question_set_type == 'flashcard') {
                    $question_type = 7;
                }
                else
                    $question_type = self::$type[trim($question['question_type'])];

                $standard_id = escape(@$question['standard_id']);
                $question_no = intval(@$question['question_no']);
                $hint = escape(@$question['hint']);
                $is_hint = !empty($hint) ? 1 : 0;
                $sort_order = intval(@$question['order']);

                $question_id = $question['id'];
                if ($question_id != 0) {
                    $sql = "UPDATE 
                            `tsc`.`assessments_questions`
                        SET
                            `question_type` = '{$question_type}',
                            `data` = '{$question_content}',
                            `special_text` = '{$special_text}',
                            `standard_id` = '{$standard_id}',
                            `sort_order` = '{$sort_order}',
                            `is_hint` = '{$is_hint}',
                            `hint` = '{$hint}',
                            `question_no` = '{$question_no}'
                        WHERE
                            `id` = $question_id
                        LIMIT 1;";
                    query($sql, __LINE__);
                } else {
                    $sql = "INSERT INTO 
                            `tsc`.`assessments_questions`
                        SET
                            `school_id` = '{$school_id}',
                            `created_by` = '{$created_by}',
                            `date_created` = NOW(),
                            `question_type` = '{$question_type}',
                            `data` = '{$question_content}',
                            `special_text` = '{$special_text}',
                            `standard_id` = '{$standard_id}',
                            `question_no` = '{$question_no}',
                            `sort_order` = '{$sort_order}',
                            `is_hint` = '{$is_hint}',
                            `hint` = '{$hint}',
                            `question_set_id` = '{$question_set_id}'
                        ;";

                    $question_id = save($sql, __LINE__);

                    // update `id` of each new question set
                    $data['questionSets'][$_qsid]['questions'][$_qid]['id'] = $question_id;
                }

                // update / insert assessment answers for each question
                $answer_type = self::$format[$question['answer']['answer_format']];
                $answer_format = $question_set_type == 'visual' ? 6 : self::$type[$question['answer']['answer_type']];

                $all_options = [];

                // for `free-response` answer there should be only one entry
                if ($answer_type == 2) {
                    $all_options[] = array_pop($question['answer']['options']);
                } else {
                    $all_options = $question['answer']['options'];
                }

                // entry for options
                foreach ($all_options as $_opid => $option) {
                    $answer_id = $option['id'];
                    $is_correct = empty($option['is_correct']) ? 0 : (boolean) $option['is_correct'];
                    $content = escape($option['content']);
                    $sort_order = intval(@$option['order']);
                    $uuid = $answer_format == 6 ? escape($option['uuid']) : '';
                    $guide = !empty($option['guide']) ? escape($option['guide']) : '';
                    $is_guide = !empty($guide);

                    if ($answer_id != 0) {
                        $sql = "UPDATE 
                            `tsc`.`assessments_answers`
                        SET
                            `answer_type` = '{$answer_type}',
                            `answer_format` = '{$answer_format}',
                            `is_correct` = '{$is_correct}',
                            `sort_order` = '{$sort_order}',
                            `uuid` = '{$uuid}',
                            `is_guide` = '{$is_guide}',
                            `guide` = '{$guide}',
                            `data` = '{$content}'
                        WHERE
                            `id` = $answer_id
                        LIMIT 1;";
                        query($sql, __LINE__);
                    } else {
                        $sql = "INSERT INTO 
                                    `tsc`.`assessments_answers`
                                SET
                                    `school_id` = '{$school_id}',
                                    `created_by` = '{$created_by}',
                                    `date_created` = NOW(),
                                    `answer_type` = '{$answer_type}',
                                    `answer_format` = '{$answer_format}',
                                    `is_correct` = '{$is_correct}',
                                    `data` = '{$content}',
                                    `sort_order` = '{$sort_order}',
                                    `uuid` = '{$uuid}',
                                    `is_guide` = '{$is_guide}',
                                    `guide` = '{$guide}',
                                    `question_id` = '{$question_id}'
                                ;";

                        $answer_id = save($sql, __LINE__);

                        // update `id` of each new answer set
                        $data['questionSets'][$_qsid]['questions'][$_qid]['answer']['options'][$_opid]['id'] = $answer_id;
                    }
                }
            }
        }
        return $data;
    }

    // internal private method for delete assessment answer(s)
    // from `assessments_answers` table
    private static function _deleteAnswers($key = '', $idx = []) {
        if (empty($key) || empty($idx))
            return false;

        // delete answers
        $_idx = implode(',', $idx);
        $sql = "delete from `tsc`.`assessments_answers` where `" . $key . "` in (" . $_idx . ");";
        return delete($sql, __LINE__);
    }

    // internal private method for delete assessment questions(s)
    // from `assessments_questions` table
    private static function _deleteQuestions($key = '', $idx = []) {
        if (empty($key) || empty($idx))
            return false;

        $_idx = implode(',', $idx);

        // find all question `id` and delete all answer with those ids
        $res = true;
        $sql = "select id from `tsc`.`assessments_questions` where `" . $key . "` in (" . $_idx . ");";
        $q_ids = query($sql, __LINE__);
        if (!empty($q_ids)) {
            $q_ids = array_values(array_pop($q_ids));
            $res = self::_deleteAnswers('question_id', $q_ids);
        }
        if (!$res)
            return $res;

        // delete questions
        $sql = "delete from `tsc`.`assessments_questions` where `" . $key . "` in (" . $_idx . ");";
        return delete($sql, __LINE__);
    }

    // internal private method for delete assessment question set(s)
    // from `assessments_questions_sets` table
    private static function _deleteQuestionSets($key = '', $idx = []) {
        if (empty($key) || empty($idx))
            return false;

        $_idx = implode(',', $idx);

        // delete all questions belongs to this question set
        $res = self::_deleteQuestions('question_set_id', $idx);
        if (!$res)
            return $res;

        // delete question sets
        $sql = "delete from `tsc`.`assessments_questions_sets` where `" . $key . "` in (" . $_idx . ");";
        return delete($sql, __LINE__);
    }

    //  method to delete Question sets/ questions/ answer
    //  DELETE ACTION
    public static function deleteAssessment($content) {
        $id = escape($content['id']);
        $type = escape($content['type']);
        $res = false;
        switch ($type) {
            case 'questionSets':
                $res = self::_deleteQuestionSets('id', [$id]);
                break;

            case 'questions':
                $res = self::_deleteQuestions('id', [$id]);
                break;

            case 'options':
                $res = self::_deleteAnswers('id', [$id]);
                break;
        }
        return ['success' => $res];
    }

}

/**
 *  `DataSetBuilderOne` class definition 
 *  acting as a interface for all Data Set One Builder operations
 */
class DataSetBuilderOne {

    //  method to save `DataSetBuilderOneViewModel` Submit via AJAX
    //  after parsing the ViewModel JSON will save to db
    //  INSERT/ UPDATE ACTION
    public static function setDataSetOne($content) {

        global $User;

        $created_by = $User->id;

        // data
        $data = $content;

        $name = escape($data['name']);
        $description = escape($data['description']);
        $standard_id = escape($data['standard_id']);

        // update / insert data set
        $data_set_id = $data['id'];

        if ($data_set_id != 0) {
            $sql = "UPDATE 
                        `tsc`.`datasets` 
                    SET 
                        `name` = '{$name}',
                        `description` = '{$description}',
                        `standard_id` = '{$standard_id}'
                    WHERE
                        `id` = $data_set_id 
                    LIMIT 1;";
            query($sql, __LINE__);
        } else {
            $sql = "INSERT INTO 
                        `tsc`.`datasets`
                        (
                            `created_by`,
                            `name`,
                            `description`,
                            `standard_id`
                        )
                    VALUES
                        (
                            $created_by,
                            '{$name}',
                            '{$description}',
                            '{$standard_id}'
                        );";

            $data_set_id = save($sql, __LINE__);

            // update the view Model data set `id`
            $data['id'] = $data_set_id;
        }

        // update/ insert data set fields
        if (!empty($data['fields'])) {
            foreach ($data['fields'] as $index => $field) {

                $data_set_field_id = $field['id'];
                $name = escape($field['name']);
                $type = escape($field['type']);

                // update
                if ($data_set_field_id != 0) {
                    $sql = "UPDATE 
                                `tsc`.`datasets_fields` 
                            SET 
                                `name` = '{$name}',
                                `type` = '{$type}'
                            WHERE 
                                `id` = $data_set_field_id
                            LIMIT 1;";
                    query($sql, __LINE__);
                } else {
                    $sql = "INSERT INTO 
                                `tsc`.`datasets_fields`
                                (
                                    `dataset_id`,
                                    `name`,
                                    `type`,
                                    `created_by`
                                )
                            VALUES
                                (
                                    '{$data_set_id}',
                                    '{$name}',
                                    '{$type}',                                        
                                    $created_by
                                );";

                    $data_set_field_id = save($sql, __LINE__);

                    // update the view Model data set field `id`
                    $data['fields'][$index] = $data_set_field_id;
                }
            }
        }
        return ['success' => true, 'dataset_id' => $data_set_id];
    }

    //  get data set
    //  required valid data set `id`
    //  READ ACTION
    //
    //  @return:
    //          A data set one JSON
    public static function getDataSetOne($data_set_one_id) {

        // default
        $data_set_one_id = escape($data_set_one_id);
        $dataSet = ['id' => $data_set_one_id, 'name' => '', 'fields' => []];

        if (empty($data_set_one_id))
            return $dataSet;

        // get data set name
        $sql = "select
                    ds.*, ss.identifier as ss_identifier, ss.standard as ss_standard
                from
                    datasets ds
                left join
                    standards_standards ss on ss.id = ds.standard_id
                where ds.id = $data_set_one_id limit 1;";

        $res = query($sql, __LINE__);
        $res = array_values($res);

        $dataSet['name'] = $res[0]['name'];
        $dataSet['description'] = $res[0]['description'];
        $dataSet['standard_id'] = $res[0]['standard_id'];
        $dataSet['ss_standard'] = $res[0]['ss_standard'];
        $dataSet['ss_identifier'] = $res[0]['ss_identifier'];

        // get data set fields
        $sql = "select id, name, type from `tsc`.`datasets_fields` where `dataset_id` = $data_set_one_id;";
        $res = query($sql, __LINE__);
        if (!empty($res)) {
            foreach ($res as $id => $field) {
                $dataSet['fields'][] = ['id' => $field['id'], 'name' => $field['name'], 'type' => $field['type']];
            }
        }

        return $dataSet;
    }

    //  delete data set field
    //  required a valid data set field id
    //  DELETE ACTION
    public static function deleteDataSetOne($data_set_one_field_id) {
        $data_set_one_field_id = escape($data_set_one_field_id);
        $sql = "delete from `tsc`.`datasets_fields` where `id` = $data_set_one_field_id limit 1;";
        return delete($sql, __LINE__);
    }

}

/**
 *  `DataSetBuilderTwo` class definition 
 *  acting as a interface for all Data Set Two Builder operations
 */
class DataSetBuilderTwo {

    //  method to save `DataSetBuilderOneViewModel` Submit via AJAX
    //  after parsing the ViewModel JSON will save to db
    //  INSERT/ UPDATE ACTION
    public static function setDataSetTwo($content) {

        global $User;

        $created_by = $User->id;

        // data
        $data = $content;

        // update / insert data set items
        $data_set_id = escape($data['id']);

        if (!empty($data['items'])) {

            // set all data set items
            foreach ($data['items'] as $_item_index => $item) {

                $sort_order = escape($item['sort_order']);
                $datset_item_id = escape($item['id']);

                if ($datset_item_id != 0) {
                    $sql = "UPDATE 
                                `tsc`.`datasets_items` 
                            SET 
                                `sort_order` = $sort_order
                            WHERE 
                                `id` = $datset_item_id 
                            LIMIT 1;";
                    query($sql, __LINE__);
                } else {
                    $sql = "INSERT INTO 
                                `tsc`.`datasets_items`
                                (
                                    `datasets_id`,
                                    `sort_order`
                                )
                            VALUES
                                (
                                    $data_set_id,
                                    $sort_order
                                );";

                    $datset_item_id = save($sql, __LINE__);

                    // update the view Model data set `id`
                    $data['items'][$_item_index]['id'] = $datset_item_id;
                }

                // if fields ie. data set data exists then save em
                if (!empty($item['fields'])) {

                    foreach ($item['fields'] as $findex => $data) {

                        $dataset_data_id = escape($data['id']);
                        $datasets_field_id = escape($data['field_id']);
                        $content = escape($data['content']);

                        if ($dataset_data_id != 0) {
                            $sql = "UPDATE 
                                        `tsc`.`datasets_data` 
                                    SET 
                                        `data` = '{$content}'
                                    WHERE 
                                        `id` = $dataset_data_id 
                                    LIMIT 1;";
                            query($sql, __LINE__);
                        } else {
                            $sql = "INSERT INTO 
                                        `tsc`.`datasets_data`
                                        (
                                            `datasets_id`,
                                            `datasets_field_id`,
                                            `datasets_item_id`,
                                            `data`,
                                            `created_by`
                                        )
                                    VALUES
                                        (
                                            $data_set_id,
                                            $datasets_field_id,
                                            $datset_item_id,
                                            '{$content}',
                                            $created_by
                                        );";

                            $dataset_data_id = save($sql, __LINE__);

                            // update the view Model data set `id`
                            $data['items'][$_item_index]['fields'][$findex]['id'] = $dataset_data_id;
                        }
                    }
                }
            }
        }

        return ['success' => true, 'data' => $data];
    }

    //  get data set items and fields data
    //  required valid data set `id`
    //  READ ACTION
    //
    //  @return:
    //          A data set one JSON
    public static function getDataSetTwo($dataset_id) {

        global $User;

        $dataset_id = escape($dataset_id);

        //  get data set and items
        $created_by = $User->id;

        //  get data set name
        $name = get_field('datasets', 'name', ['id' => $dataset_id]);

        //  default data set
        $_default = [];

        //  Main result
        $result_data = ['id' => $dataset_id, 'name' => $name, 'item_count' => 1, 'items' => []];

        //  get data set's data
        $sql = "select 
                    dsi.id item_id,
                    dsi.sort_order,
                    dsf.id field_id,
                    dsf.type,
                    dsf.name field_name,
                    dsd.id data_id,
                    dsd.data content
                from
                    datasets_fields dsf 
                        left join
                    datasets_items dsi ON dsi.datasets_id = $dataset_id
                        left join
                    datasets_data dsd ON dsd.datasets_item_id = dsi.id
                        and dsd.datasets_field_id = dsf.id
                where
                    dsf.dataset_id = $dataset_id
                order by dsi.sort_order asc;";

        $data_items = query($sql, __LINE__);

        if (!empty($data_items)) {

            //  get item ids
            $items = do_extract($data_items, 'item_id|sort_order');
            $items = array_filter(array_values(array_combine(array_map(function ($i) {
                                                return $i['item_id'];
                                            }, $items), $items)), function($i) {
                        return !is_null($i['item_id']);
                    });

            if (!empty($items)) {

                //  result data
                $result_data['item_count'] = count($items);

                foreach ($items as $x => $item) {

                    //  main result
                    $result_data['items'][$x] = ['id' => $item['item_id'], 'sort_order' => $item['sort_order'], 'fields' => []];

                    //  default data set
                    $_default = ['id' => 0, 'sort_order' => 0, 'fields' => []];

                    //  get data for current item
                    $fields = array_values(do_extract($data_items, '[item_id=' . $item['item_id'] . ']'));

                    //  retrive data
                    foreach ($fields as $y => $data) {
                        $result_data['items'][$x]['fields'][$y] = [
                            'id' => $data['data_id'],
                            'field_id' => $data['field_id'],
                            'type' => $data['type'],
                            'name' => $data['field_name'],
                            'content' => $data['content']];

                        //  default data set
                        $_default['fields'][] = [
                            'id' => 0,
                            'field_id' => $data['field_id'],
                            'type' => $data['type'],
                            'name' => $data['field_name'],
                            'content' => ''];
                    }
                }
            } else {

                //  if not data set item found then $result_data empty
                //  prepare a default data
                $_default = ['id' => 0, 'sort_order' => 1, 'fields' => []];
                foreach ($data_items as $item) {
                    $_default['fields'][] = [
                        'id' => 0,
                        'field_id' => $item['field_id'],
                        'type' => $item['type'],
                        'name' => $item['field_name'],
                        'content' => ''];
                }

                //  set result with default data set
                $result_data['items'][] = $_default;
            }
        }

        return ['data' => $result_data, '_default' => $_default];
    }

    //  delete data set item and data fields
    //  required a valid data set field id
    //  DELETE ACTION
    public static function deleteDataSetTwo($data_set_one_field_id) {
        $data_set_one_field_id = escape($data_set_one_field_id);
        $sql = "delete from `tsc`.`datasets_fields` where `id` = $data_set_one_field_id limit 1;";
        return delete($sql, __LINE__);
    }

}

/**
 *  `FlashCardCreator` class definition 
 *  acting as a interface for all Flashcard Creator page activities
 */
class FlashCardCreator {

    //  get all datasets of logged in user (modal 1)
    public static function getDataSets() {
        global $User;
        $created_by = $User->id;

        $datasets = get_record_by_field('datasets', 'created_by', $created_by);

        if (!empty($datasets)) {
            $datasets = array_map(function($i) {
                        return ['id' => $i['id'], 'name' => $i['name']];
                    }, $datasets);
        }

        return ['data' => $datasets];
    }

    //  get all fields for a data set (modal 2)
    public static function getDataSetFields($dataset_id) {
        $res = [];

        $sql = 'select id, name, type from datasets_fields where dataset_id = ' . $dataset_id . ' order by id asc;';
        $res = query($sql, __LINE__);

        //  get all completed cards
        //$completed_cards = self::getCompletedFlashCard($dataset_id);

        if (!empty($res))
            $res = array_values($res);

        return ['data' => $res];
    }

    //  set content to flash card
    public static function setFlashCardContent($dataset_id, $card_name) {
        global $User;
        $created_by = $User->id;

        $sql = "insert into
                    flashcards 
                    (
                        dataset_id,
                        name,
                        created_by,
                        is_content_set
                    )
                values
                    (
                        '{$dataset_id}',
                        '{$card_name}',
                        $created_by,
                        1
                    );";
        $flashcard_id = save($sql, __LINE__);
        return $flashcard_id;
    }

    //  set flashcard layout
    public static function setFlashCardLayout($content) {

        if (empty($content))
            return ['success' => false];

        global $User;
        $created_by = $User->id;

        $dataset_id = $content['dataset_id'];
        $card_name = escape($content['card_name']);
        $fronts = $content['layouts']['front'];
        $backs = $content['layouts']['back'];
        $quiz_type = ['written' => 1, 'multiple' => 2];

        $quiz_answer_type = $quiz_type[escape($content['quiz_answer_type'])];



        //  first save the content set to `flashcard` table
        //  create new flashcard
        $flashcard_id = self::setFlashCardContent($dataset_id, $card_name);

        foreach ($fronts as $front) {
            $datasets_field_id = escape($front['id']);
            $sort_order = escape($front['sort_order']);

            $sql = "insert into
                        flashcards_layout
                        (   
                            flashcards_id,
                            datasets_field_id,
                            side_id,
                            sort_order,
                            quiz_answer_type,
                            created_by
                        )
                    values
                        (
                            '{$flashcard_id}',
                            '{$datasets_field_id}',
                            1,
                            '{$sort_order}',
                            {$quiz_answer_type},
                            $created_by
                        );";
            $flashcard_layout_id = save($sql, __LINE__);
        }

        foreach ($backs as $back) {
            $datasets_field_id = escape($back['id']);
            $sort_order = escape($back['sort_order']);

            $sql = "insert into
                        flashcards_layout
                        (
                            flashcards_id,
                            datasets_field_id,
                            side_id,
                            sort_order,
                            quiz_answer_type,
                            created_by
                        )
                    values
                        (
                            '{$flashcard_id}',
                            '{$datasets_field_id}',
                            2,
                            '{$sort_order}',
                            {$quiz_answer_type},
                            $created_by
                        );";
            $flashcard_layout_id = save($sql, __LINE__);
        }

        return ['success' => true, 'flashcard_id' => $flashcard_id, 'flashcard_name' => $card_name];
    }

    //  get all flashcard save under selected content set
    public static function getFlashCard($flashcard_id) {
        global $User;
        $created_by = $User->id;

        $sql = "select 
                    fc.name card_name, fcl.id, df.name, df.type, dd.data , dd.datasets_item_id, fcl.id field_id, side_id
                from
                    flashcards fc
                left join
                    flashcards_layout fcl ON fcl.flashcards_id = fc.id
                left join
                    datasets_data dd ON dd.datasets_field_id = fcl.id
                left join
                    datasets_fields df ON df.id = fcl.id
                where fc.id = $flashcard_id
                order by dd.datasets_item_id, fcl.id;";

        $res = query($sql, __LINE__);
        $res = array_values($res);
        return ['data' => $res, 'card_name' => $res[0]['card_name']];
    }

}

/**
 *  `FlashcardViewer` class definition 
 *  Interface for all Flashcard Viewer page activities
 */
class FlashcardViewer {

    public static $type = [1 => 'text', 2 => 'image', 3 => 'audio', 4 => 'math', 5 => 'video'];

    public static function getFlashcardViewer($flashcard_id) {

        //  get corresponding dataset_id for current $flashcard_id
        $sql = "select name, dataset_id from flashcards where id=" . $flashcard_id . ";";
        $res = query($sql, __LINE__);
        if (!empty($res))
            $res = array_values($res);

        $dataset_id = $res[0]['dataset_id'];
        $name = $res[0]['name'];

        //  get all layout for current $flashcard_id
        $sql = "select 
                    datasets_field_id, side_id
                from
                    flashcards_layout
                where
                    flashcards_id = $flashcard_id
                order by
                    side_id, sort_order asc;";

        $res = query($sql, __LINE__);
        $layouts = array_values($res);

        //  get all content set items for this dataset_id
        $sql = "select 
                    datasets_item_id, datasets_field_id, data, type
                from
                    datasets_fields df
                right join
                    datasets_data dd on dd.datasets_field_id = df.id
                where
                    dataset_id = $dataset_id
                order by
                    datasets_item_id, datasets_field_id;";

        $res = query($sql, __LINE__);
        $content_set_data = array_values($res);

        $result = [];
        $count = -1;
        $cur_item_id = null;

        foreach ($content_set_data as $item) {
            $ds_item_id = $item['datasets_item_id'];
            $field_id = $item['datasets_field_id'];

            if ($cur_item_id != $ds_item_id) {
                $cur_item_id = $ds_item_id;
                ++$count;
            }
            $layout = do_extract($layouts, '[datasets_field_id=' . $item['datasets_field_id'] . ']');
            if (!empty($layout)) {
                $result[$count]['items'][] = ['side_id' => array_pop($layout)['side_id'], 'data' => $item['data'], 'type' => self::$type[$item['type']]];
            }
        }
        return ['name' => $name, 'data' => $result];
    }

}

class MedicalProfessionals {

    public static function get_practice_areas() {
        $sql = "select * from practice_area";
        $practice_areas = query($sql, __LINE__);
        return (empty($practice_areas) ? '' : $practice_areas);
    }

    public static function remove_medical_professional_from_user($content) {
        $sql = "Delete from medical_professionals_users where id=" . escape($content['id']);
        return API::query($sql, __LINE__);
    }

    public static function get_user_titles() {
        $sql = "select * from users_titles";
        $users_titles = query($sql, __LINE__);
        return $users_titles;
    }

    public static function get_medical_professionals_users($content) {
        $sql = " SELECT mp.user_id AS userId,p.id AS pkey,prof.first_name, prof.last_name, c. * , pa.name AS practice_area
FROM `medical_professionals_users` p
LEFT JOIN medical_professionals mp ON p.professional_id = mp.id
LEFT JOIN practice_area pa ON mp.practice_area_id = pa.id
LEFT JOIN contact c ON mp.user_id = c.user_id
LEFT JOIN users prof ON mp.user_id = prof.id 
WHERE p.user_id =" . escape($content['userId']);
        $students = API::query($sql, __LINE__);
        return (empty($students) ? array() : $students);
    }

    public static function set_medical_professionals($content = array()) {

        $frmdata = array();
        foreach ($content as $data) {
            if (strstr($data['name'], "[]")) {
                $frmdata[str_replace("[]", '', $data['name'])][] = $data['value'];
            } else {
                $frmdata[$data['name']] = $data['value'];
            }
        }

        $pmyarr = array('primary_email', 'primary_address', 'primary_phone');
        foreach ($pmyarr as $prime) {
            if (!isset($frmdata[$prime])) {
                $frmdata[$prime] = '';
            }
        }
        $practice_area_id = $frmdata['practiceArea'];
        $sql = "Insert into users(first_name,last_name,title_id,email,gender) VALUES(";

        $usersfields = array('first_name', 'last_name', 'title_id', 'email', 'gender');

        foreach ($usersfields as $fields) {
            if (!empty($frmdata[$fields])) {
                if (is_array($frmdata[$fields])) {
                    $sql.= "'" . $frmdata[$fields][0] . "'";
                } else {
                    $sql.= "'" . $frmdata[$fields] . "'";
                }
                if ($fields != 'gender') {
                    $sql.= ",";
                }
            } else {
                print_r($frmdata);
                echo "$fields missing \n  "; //  return false;
            }
        }

        $sql .= ");";
        if (!empty($sql)) {
            $user_id = save($sql, __LINE__);


            $contactSql = "INSERT INTO contact (user_id ,field ,value ,type ,primary_contact)VALUES (";

            foreach ($frmdata['email'] as $k => $email) {
                $isPrimary = 0;
                if (!empty($email)) {
                    if ($frmdata['primary_email'] == $email) {
                        $isPrimary = 1;
                    }
                    $sql = $contactSql . "$user_id,'email','" . $email . "','" . $frmdata['email_type'][$k] . "',$isPrimary)";
                    save($sql, __LINE__);
                }
            }
            foreach ($frmdata['phone'] as $k => $phone) {
                $isPrimary = 0;
                if (!empty($phone)) {
                    if ($frmdata['primary_phone'] == $phone) {
                        $isPrimary = 1;
                    }
                    $sql = $contactSql . "$user_id,'phone','" . $phone . "','" . $frmdata['phone_type'][$k] . "',$isPrimary)";
                    save($sql, __LINE__);
                }
            }
            foreach ($frmdata['address'] as $k => $address) {
                $isPrimary = 0;
                if (!empty($address)) {
                    if ($frmdata['primary_address'] == $address) {
                        $isPrimary = 1;
                    }
                    $sql = $contactSql . "$user_id,'address','" . $address . "','" . $frmdata['address_type'][$k] . "',$isPrimary)";
                    save($sql, __LINE__);
                }
            }

            if (!empty($user_id)) {
                $sql = "insert into medical_professionals (user_id,practice_area_id) VALUES ($user_id,$practice_area_id);";
                $prof_id = save($sql, __LINE__);
                if (!empty($prof_id)) {
                    global $User;
                    $created_by = $User->id;
                    $date = date('Y-m-d H:i:s');
                    $sql = "insert into medical_professionals_users (user_id,professional_id,date_create,created_by) VALUES (" . $frmdata['user_id'] . "," . $prof_id . ",'$date',$created_by);";
                    $prof_users_id = save($sql, __LINE__);
                    return true;
                } else {
                    return FALSE;
                }
            }
        } else {
            return false;
        }
    }

    public static function set_medical_professional_users($content = array()) {

        if (!empty($content)) {
            if (!empty($content['professional_id'])) {
                global $User;
                $created_by = $User->id;
                $date = date('Y-m-d H:i:s');
                foreach ($content['professional_id'] as $pid) {
                    if (!empty($pid)) {
                        $sql = "insert into medical_professionals_users (user_id,professional_id,date_create,created_by) VALUES (" . $content['user_id'] . "," . $pid . ",'$date',$created_by);";
                        $prof_users_id = save($sql, __LINE__);
                    }
//
                    // $prof_users_id = save($sql, __LINE__);
                    // echo "\n $prof_users_id id id   \n";
                }
                if (!empty($prof_users_id)) {
                    return true;
                } else {
                    return FALSE;
                }
            }
        } else {
            return false;
        }
    }

    public static function get_medical_professionals($content = array()) {
        $add_sql = " WHERE mp.id NOT
IN (

SELECT DISTINCT medical_professionals_users.`professional_id`
FROM `medical_professionals_users` , medical_professionals
WHERE medical_professionals_users.`user_id` =".$content['user_id'].")";        

        $sql = " SELECT t.name AS title, parea.name AS practice_name, mp.id AS pkey, mp.user_id AS mp_user_id, p.user_id, mp.practice_area_id, u.first_name, u.last_name, c. * , pu.first_name AS user_first_name, pu.last_name AS user_last_name
FROM medical_professionals mp
LEFT JOIN practice_area parea ON mp.practice_area_id = parea.id
LEFT JOIN users u ON mp.user_id = u.id
LEFT JOIN users_titles t ON u.title_id = t.id
LEFT JOIN contact c ON u.id = c.user_id
AND c.primary_contact =1
LEFT JOIN medical_professionals_users p ON mp.id = p.professional_id
LEFT JOIN users pu ON p.user_id = pu.id  
{$add_sql}
    
";
//echo $sql;

        $medicalProfessionals = API::query($sql, __LINE__);
        if ($medicalProfessionals)
            return $medicalProfessionals;
        return array();
    }

}

/**
 *  `FlashcardQuiz` class definition 
 *  Interface for all Flashcard Quiz page activities
 */
class FlashcardQuiz {

    public static $type = [1 => 'text', 2 => 'image', 3 => 'audio', 4 => 'math', 5 => 'video'];

    //  get list of all flashcard by Auth user
    public static function getAllFlashcardsByAuthUser() {
        global $User;
        $created_by = $User->id;

        $sql = "select id, name from flashcards;";
        $res = query($sql, __LINE__);
        $res = array_values($res);
        return $res;
    }

    public static function getFlashcardQuiz($flashcard_id) {

        $quiz_answer_type = [1 => 'written', 2 => 'multiple'];

        //  get corresponding dataset_id for current $flashcard_id
        $sql = "select name, dataset_id from flashcards where id=" . $flashcard_id . ";";
        $res = query($sql, __LINE__);
        if (!empty($res))
            $res = array_values($res);

        $dataset_id = $res[0]['dataset_id'];
        $name = $res[0]['name'];

        //  get all layout for current $flashcard_id
        $sql = "select 
                    datasets_field_id, side_id, quiz_answer_type
                from
                    flashcards_layout
                where
                    flashcards_id = $flashcard_id
                order by
                    side_id, sort_order asc;";

        $res = query($sql, __LINE__);
        $layouts = array_values($res);

        //  extract dataset field ids
        $dataset_field_idx = do_extract($layouts, 'datasets_field_id');
        $dataset_field_id_str = implode(',', $dataset_field_idx);

        //  get all content set items for this dataset_id
        $sql = "select 
                    datasets_item_id, datasets_field_id, data, type
                from
                    datasets_fields df
                right join
                    datasets_data dd on dd.datasets_field_id = df.id
                where
                    dataset_id = $dataset_id
                    and datasets_field_id in ($dataset_field_id_str)
                order by
                    datasets_item_id, datasets_field_id;";

        $res = query($sql, __LINE__);
        $content_set_data = array_values($res);

        $result = [];
        $count = -1;
        $cur_item_id = null;

        $assessment_question = [];
        $_contents = $content_set_data;

        foreach ($content_set_data as $item) {
            $ds_item_id = $item['datasets_item_id'];
            $field_id = $item['datasets_field_id'];

            if ($cur_item_id != $ds_item_id) {
                $cur_item_id = $ds_item_id;
                ++$count;
            }
            $layout = do_extract($layouts, '[datasets_field_id=' . $field_id . ']');
            if (!empty($layout)) {
                $l = array_pop($layout);
                $quiz_ans_type = $l['quiz_answer_type'];
                $side_id = $l['side_id'];
                $item_type = $item['type'];
                $ans_format = $quiz_ans_type == 1 ? 'free-response' : 'multi-choice';

                $a_o = 1;

                if ($side_id == 1) {
                    $result[$count]['questions'][] = [
                        'side_id' => $side_id,
                        'data' => $item['data'],
                        'type' => self::$type[$item_type],
                        'quiz_ans_type' => $quiz_ans_type
                    ];
                } else if ($side_id = 2) {
                    $result[$count]['answers'] = [
                        '_type' => self::$type[$item_type],
                        'answer_type' => self::$type[$item_type],
                        '_format' => $ans_format,
                        'answer_format' => $ans_format,
                    ];
                    $result[$count]['answers']['options'][] = [
                        'id' => 0,
                        'uuid' => '',
                        'is_correct' => true,
                        'order' => $a_o,
                        'content' => $item['data'],
                        'guide' => '',
                        'is_guide' => false
                    ];

                    $options = do_extract($_contents, '[datasets_field_id=' . $field_id . ']');
                    foreach ($options as $opt) {
                        if ($opt['datasets_item_id'] != $item['datasets_item_id'] && count($result[$count]['answers']['options']) < 4) {
                            $result[$count]['answers']['options'][] = [
                                'id' => 0,
                                'uuid' => '',
                                'is_correct' => false,
                                'order' => ++$a_o,
                                'content' => $opt['data'],
                                'guide' => '',
                                'is_guide' => false
                            ];
                        }
                    }
                    shuffle($result[$count]['answers']['options']);
                }
                //  prepare assessments questions and answers
                $assessment_question[$count] = [
                    'id' => 0,
                    '_type' => 'flashcard',
                    'question_type' => 'flashcard',
                    'content' => empty($result[$count]['questions']) ? [] : $result[$count]['questions'],
                    'special_text' => '',
                    'question_no' => $count + 1,
                    'order' => $count + 1,
                    'ss_identifier' => '',
                    'ss_standard' => '',
                    'standard_id' => null,
                    'is_hint' => 0,
                    'hint' => '',
                    'hint_vis' => false,
                    'answer' => isset($result[$count]['answers']) ? $result[$count]['answers'] : []
                ];
            }
        }
        return $assessment_question;
    }
}


/*
 *  GradeBookStandardView Class
 * */

class GradeBookStandardView {

    public static function getGradesListForStandardView($section_id) {

        //  get standard_based_id for corresponding section_id
        $sql = "select id, standards_based_id from sections where id = " . $section_id;
        $res = API::query($sql, __LINE__);
        if (empty($res)) {
            return ['success' => false,
                'message' => 'No grading scale selected.  Please contact your system administrator.'];
        }
        $res = array_values($res);
        $standard_based_id = $res[0]['standards_based_id'];
        if (!$standard_based_id) {
            return ['success' => false,
                'message' => 'No grading scale selected.  Please contact your system administrator.'];
        }

        //  get all grades that not belong to `grades_sections` table
        //  and entry to `grades_sections` table and then retrieve
        //  grades list (JSON) from that table and use

        $sql = "select
                    sec.standards_based_id standards_based_id,
                    sec.is_show_letters,
                    g.id grade_id,
                    gsm.is_gpa,
                    gsm.is_graduation,
                    gsm.value,
                    gsm.min_score,
                    g.name,
                    g.description
                from
                    sections sec
                        left join
                    grades_scales_map gsm ON gsm.grading_scale_id = sec.standards_based_id
                        left join
                    grades g ON g.id = gsm.grade_id
                where
                    sec.id = $section_id
                        and g.id not in (select
                            grade_id
                        from
                            grades_sections
                        where
                            section_id = $section_id)
                order by value desc;";
        $new_grades = API::query($sql, __LINE__);
        if (!empty($new_grades)) {
            $new_grades = array_values($new_grades);
            $row = count($new_grades);

            $new_entries = "insert into `grades_sections`
                                (section_id, grade_id, name, min_score, value, description)
                           values ";
            foreach ($new_grades as $key => $grade) {
                $grade_id = $grade['grade_id'];
                $min_score = $grade['min_score'];
                $value = $grade['value'];
                $description = $grade['description'];
                $name = $grade['name'];

                $new_entries .= "($section_id, $grade_id, '{$name}', $min_score, $value, '{$description}')";
                $new_entries .= ($key < $row - 1) ? ' , ' : '';
            }
            $new_entries .= ";";
            API::query($new_entries, __LINE__);
        }

        //  get grades list (JSON) from `grades_sections` table
        $sql = "select
                    grade_id, name letter, min_score minScore, value
                from
                    grades_sections
                where
                    section_id = $section_id
                    and grade_id in (select
                                        grade_id
                                    from
                                        grades_scales_map
                                    where
                                        grading_scale_id = $standard_based_id
                                    )
                order by value desc;";
        $res = API::query($sql, __LINE__);
        $grades = array_values($res);

        //  Check IS Letter Grade Show to Overall grade column
        $res = query("select is_show_letters from sections where id = " . $section_id, __LINE__);
        $is_show_letters = $res[0]['is_show_letters'];

        return ['success' => true, 'grades' => $grades, 'is_show_letters' => $is_show_letters];
    }

    public static function ellipse($str) {
        if (strlen($str) > 110) $str = substr($str,0,110) . '...';
        return $str;
    }

    public static function getCategoriesAndStandards($content) {
        $section_id = $content['section_id'];
        $subject_sql = empty($content['subject_id']) ? "" : " AND sc.id = {$content['subject_id']} ";
        $grading_period_id = $content['grading_period_id'];

        $sql = "SELECT
                    sc.name
                    ,sc.id categoryId
                    ,ss.*
                    ,ac.weight
                FROM standards_standards ss
                JOIN standards_categories sc ON sc.id = ss.category_id
                JOIN standards_subjects sub ON sub.id = sc.subject_id
                JOIN assignment_categories ac ON ac.standards_categories_id = ss.id
                WHERE ac.section_id = $section_id
                AND ac.grading_period_id = $grading_period_id
                $subject_sql
                ORDER BY sub.sort_order, sc.name, ss.sort_order, ss.identifier";
        $res = API::query($sql, __LINE__);
        $result = ['standards' => [], 'weights' => [], 'categories' => []];
        $standardCategories = [];

        if( !empty($res) ) {
            foreach($res as $entry) {

                $category_name = strlen($entry['name']) > 12 ? substr(explode(':',$entry['name'])[0], 0, 10) . '...' : $entry['name'];

                $result['standards'][] = [
                    'standard_id' => $entry['id'],
                    'standard' => $entry['identifier'] . ': ' . $entry['standard'],
                    'standard_trim' => $entry['identifier'] . ': ' . self::ellipse($entry['standard']),
                    'categoryName_trim' => $category_name,
                    'categoryName' => $entry['name'],
                    'categoryId' => $entry['category_id'],
                    'weight' => is_null($entry['weight']) ? 0 : $entry['weight'],
                    'max_points' => 100 // for all standard max_points = 100
                ];

                $standardCategories[] = [
                    'id' => $entry['category_id'],
                    'weight' => is_null($entry['weight']) ? 0 : $entry['weight'],
                    'name' => $entry['name']
                ];
            }

            //  pick all weights of standards
            $weights = array_map(function($i){
                    return [ $i['categoryId'] => $i['weight'] ];
            }, $result['standards']);
            $result['weights'] = $weights;
            $result['categories'] = $standardCategories;
        }
        return $result;
    }

    public static function saveStudentGradeForStandardMode($content){
        global $User;
        $student_id = $content['user_id'];
        $section_id = $content['section_id'];
        $grade_id = empty($content['grade_id']) ? 'NULL' : $content['grade_id'];
        $grading_period_id = $content['grading_period_id'];
        $standard_id = $content['standard_id'];
        $notes = empty($content['notes']) ? NULL : escape($content['notes']);
        $created_by = $User->id;
        $teacher_id = $User->id;
        $_id = false;
        $sql = "select id
                from standards_grades
                where
                    user_id = $student_id
                    and section_id = $section_id
                    and grading_period_id = $grading_period_id
                    and standard_id = $standard_id
                limit 1;";
        $res = API::query($sql, __LINE__);
        if( empty($res) ) {
            $sql = "insert into standards_grades
            (section_id, user_id, grade_id, grading_period_id, standard_id, notes, created_by, teacher_id)
            values
            ($section_id, $student_id, $grade_id, $grading_period_id, $standard_id, '{$notes}', $created_by, $teacher_id);";
            $_id = save($sql, __LINE__);
        } else {
            $sql = "update standards_grades
                    set grade_id = $grade_id, notes = '{$notes}'
                    where
                        user_id = $student_id
                        and section_id = $section_id
                        and grading_period_id = $grading_period_id
                        and standard_id = $standard_id
                    limit 1;";
            API::query($sql, __LINE__);
            $_id = array_pop($res)['id'];
        }
        return $_id;
    }
}

/*
 * Report Card Generator component functions
 */
class ReportCardGenerator {

    /*
     * Get list of grading periods for the school
     */
    public static function getAllGradingPeriods() {
        global $School;
        $sql = "select id, name from grading_periods where district_id = $School->district_id";
        $grading_periods = query($sql,__LINE__);
        return $grading_periods;
    }

    public static function getAllGradeLevels(){
        global $School;
        $sql = "select id, description as name
                from grade_levels gl
                right join school_grade_levels_map sglm on sglm.grade_level_id = gl.id
                where sglm.school_id = $School->id";
        $grade_levels = query($sql,__LINE__);
        return $grade_levels;
    }

    public static function getAllGPACalculations(){
        global $School;
        $sql = "select id, name from gpa_calculation";
        $gpa_calcs = query($sql,__LINE__);
        return $gpa_calcs;
    }

}


/*
 *  Assessment Taker Class
 */
class AssessmentTaker {

    // mapping over answer type and answer format
    public static $format = ['multi-choice' => 1, 'free-response' => 2];
    public static $type = ['text' => 1, 'image' => 2, 'audio' => 3, 'math' => 4, 'video' => 5, 'visual' => 6, 'flashcard' => 7];
    public static $setType = ['standard' => 1, 'visual' => 2, 'flashcard' => 3];

    public static function getTakerAssessments($assessment_id) {

        if( empty($assessment_id) ) return false;

        // get assessment using `assessment_id`
        $sql = "select id, name from `tsc`.`assessments` where `id` = $assessment_id order by `id` asc limit 1";
        $assessment = query($sql, __LINE__);

        // check if no assessment exists
        if (empty($assessment))
            return false;

        $assessment = array_pop($assessment);
        if (!empty($assessment)) {

            /*
             *  keep record of no. of attempt by a user
             */

            global $User;
            $user_id = $User->id;
            $sql = "insert into
                            assessments_students_attempts
                        set
                            user_id = $user_id,
                            assessment_id = $assessment_id;";
            $attempt_id = save($sql, __LINE__);

            $assessment_view_model = ['id' => $assessment['id'], 'main_heading' => $assessment['name']];

            // get question sets
            $sql = "select * from `tsc`.`assessments_questions_sets` where `assessment_id` = " . $assessment['id'] . " order by sort_order asc;";
            $question_sets = query($sql, __LINE__);

            // Preparing view Model Object
            $assessment_view_model['questionSets'] = [];
            $result = self::_prepareQuestionSet($question_sets, $assessment_view_model);
            $result['error'] = false;
            $result['attempt_id'] = $attempt_id;

            return $result;
        }
    }

    //  private method to prepare question set
    private static function _prepareQuestionSet($question_sets, $assessment_view_model, $import = false) {
        if (!empty($question_sets)) {

            $question_set_idx = do_extract($question_sets, 'id');

            // get questions
            $sql = "select aq.*, ss.identifier as ss_identifier, ss.standard as ss_standard from `tsc`.`assessments_questions` aq left join standards_standards ss on ss.id = aq.standard_id where `question_set_id` in (" . implode(',', $question_set_idx) . ") order by sort_order asc;";
            $all_questions = query($sql, __LINE__);

            if (!empty($all_questions)) {
                $question_idx = do_extract($all_questions, 'id');

                // get answers
                $sql = "select * from `tsc`.`assessments_answers` where `question_id` in (" . implode(',', $question_idx) . ") order by sort_order asc;";
                $all_answers = query($sql, __LINE__);
            }


            $f = array_flip(self::$format);
            $t = array_flip(self::$type);
            $qset_type = array_flip(self::$setType);
            $_index = $import ? 1 : 0;

            foreach ($question_sets as $question_set) {
                $qsid = $question_set['id'];
                $qset = ['id' => $import ? 0 : $qsid,
                    '_type' => $t[$question_set['context_type']],
                    'content' => $question_set['context'],
                    'context_type' => $t[$question_set['context_type']],
                    'heading' => $question_set['heading'],
                    'is_heading_visible' => !empty($question_set['heading']) ? true : false,
                    'instruction' => $question_set['instructions'],
                    'order' => $question_set['sort_order'],
                    'index_at' => ++$_index,
                    'type' => $qset_type[$question_set['type']],
                    'flashcard_id' => $question_set['flashcard_id'],
                    'visual_data' => []];

                if ($question_set['type'] == 2) {
                    $sql = 'select * from visual_identifications where id = ' . $question_set['visual_identification_id'];
                    $res = query($sql, __LINE__);
                    if (!empty($res)) {
                        $res = array_values($res);
                        $vdata['id'] = $res[0]['id'];
                        $vdata['img'] = $res[0]['img_path'];
                        //  pushing a additional field to keep track
                        //  of what field choose by student as correct answer
                        $coords = json_decode($res[0]['coords'], true);
                        $selections = array_map(function($i){
                            $i['is_choose'] = false;
                            unset($i['content']);
                            return $i;
                        }, $coords);

                        $vdata['selections'] = $selections;
                    }
                    $qset['visual_data'] = $vdata;
                }

                if (!empty($all_questions)) {
                    $questions = do_extract($all_questions, '[question_set_id=' . $qsid . ']');

                    if (!empty($questions)) {
                        foreach ($questions as $key => $question) {
                            $ques = ['id' => $import ? 0 : $question['id'],
                                'order' => $question['sort_order'],
                                'question_no' => $import ? ($key+1) : $question['sort_order'],
                                'question_type' => $t[$question['question_type']],
                                '_type' => $t[$question['question_type']],
                                'content' => $question_set['type'] == 3 ? json_decode($question['data']) : $question['data'],
                                'special_text' => $question['special_text'],
                                'standard_id' => $question['standard_id'],
                                'is_hint' => $question['is_hint'],
                                'hint' => $question['hint'],
                                'hint_vis' => empty($question['hint']),
                                'is_hint_used' => false,
                                'ss_identifier' => $question['ss_identifier'],
                                'ss_standard' => utf8_encode($question['ss_standard']),
                                'visual_data' => $qset['visual_data']];

                            $answers = do_extract($all_answers, '[question_id=' . $question['id'] . ']');
                            $ans = [];
                            if (!empty($answers)) {
                                $answers = array_values($answers);
                                $ans = [
                                    'answer_type' => $t[$answers[0]['answer_format']],
                                    '_type'  => $t[$answers[0]['answer_format']],
                                    'answer_format' => $f[$answers[0]['answer_type']],
                                    '_format' => $f[$answers[0]['answer_type']]
                                ];
                                foreach ($answers as $answer) {
                                    $ans['options'][] = ['id' => $import ? 0 : $answer['id'],
                                        'uuid' => $answer['uuid'],
                                        //'is_guide' => $answer['is_guide'],
                                        //'guide' => $answer['guide'],
                                        //'is_correct' => $answer['is_correct'],
                                        'is_choose' => false,
                                        'content' => $ans['answer_type'] == 'visual' ? '' : $answer['data'],
                                        'order' => $answer['sort_order']];
                                }
                            }

                            //exit;
                            // insert answer block to question
                            $ques['answer'] = $ans;

                            // insert question block to question set
                            $qset['questions'][] = $ques;
                        }
                    }
                }
                else
                    $qset['questions'][] = [];

                // insert question set to viewModel
                $assessment_view_model['questionSets'][] = $qset;
            }
        }

        return $assessment_view_model;
    }

    public static function setStudentAssessmentAnswers($data) {
        global $User;
        $user_id = $User->id;

        $questionSets = $data['questionSets'];
        $attempt_id = $data['attempt_id'];
        $assessment_id = $data['id'];

        foreach($questionSets as $set) {
            $questions = $set['questions'];

            foreach($questions as $question) {
                $q_id = $question['id'];
                $is_hint_used = (int)$question['is_hint_used'];

                $answer = $question['answer'];
                $answer_type = $answer['answer_type'];
                $answer_format = $answer['answer_format'];

                $options = $answer['options'];
                foreach($options as $option) {
                    $answer_id = $option['id'];
                    $is_choose = $option['is_choose'];
                    $free_response_data = '';
                    if($answer_format == 'free-response') {
                        $free_response_data = escape($option['content']);
                        $sql = "insert into
                                    assessments_students_answers
                               set
                                  user_id = {$user_id},
                                  created_by = {$user_id},
                                  assessment_id = {$assessment_id},
                                  question_id = {$q_id},
                                  answer_id = {$answer_id},
                                  free_response_data = '{$free_response_data}',
                                  attempt_id={$attempt_id},
                                  is_hint={$is_hint_used};";
                        save($sql,__LINE__);
                    } else if($is_choose) {
                        // get correct answer point
                        $res = query("select is_correct from assessments_answers
                                        where question_id = $q_id
                                              and id = $answer_id
                                        limit 1;", __LINE__);
                        if(empty($res)) {
                            $is_correct = 0;
                        } else {
                            $is_correct = $res[0]['is_correct'];
                        }
                        $points = $is_correct ? 1 : 0;
                        $sql = "insert into
                                    assessments_students_answers
                               set
                                  user_id = {$user_id},
                                  created_by = {$user_id},
                                  assessment_id = {$assessment_id},
                                  question_id = {$q_id},
                                  answer_id = {$answer_id},
                                  free_response_data = '{$free_response_data}',
                                  attempt_id={$attempt_id},
                                  points_earned = {$points},
                                  is_hint={$is_hint_used};";
                        save($sql,__LINE__);
                    }
                }
            }
        }
        return true;
    }
}

class AttendanceCopying {
    public static function update_checkbox($data){
        global $School;

        query("UPDATE att_settings_school SET is_copy = {$data['checkbox_value']} WHERE school_id = $School->id",__LINE__);
        return TRUE;
    }
}

class GradeDistribution {
    public static function update_checkbox($data){
        global $School;

        query("UPDATE grade_settings_school SET is_distribution = {$data['checkbox_value']} WHERE school_id = $School->id",__LINE__);
        return TRUE;
    }
}
class ShowOverallGrade {
    public static function update_checkbox($data){
        global $School;

        query("UPDATE grade_settings_school SET is_show_overall_grade = {$data['checkbox_value']} WHERE school_id = $School->id",__LINE__);
        return TRUE;
    }
}
class ShowAggregatePeriod {
    public static function update_checkbox($data){
        global $School;

        query("UPDATE grade_settings_school SET is_show_aggregate = {$data['checkbox_value']} WHERE school_id = $School->id",__LINE__);
        return TRUE;
    }
}
class deleteAllRequests {
    public static function delete_requests($data){

        foreach($data['requests'] as $request => $id) {
            query("delete from courses_requests where id = $id", __LINE__);
        }

        return TRUE;
    }
}

class TscWidgetsAndTabs {

    private static $pageId;
    private static $widgetId;
    private static $accessLevel;
    private static $response = ['status' => false, 'message' => 'Operation Failed.'];

    private static function getUserAccessLevel() {
        global $User, $School;
        return check_widget_permissions($User->id, self::$widgetId, $School->id);
    }

    public static function deleteWidgetFromPage($content) {
        if ( !empty($content['widget_id']) && !empty($content['page_id']) ) {
            self::$pageId = $content['page_id'];
            self::$widgetId = $content['widget_id'];
            self::$accessLevel = self::getUserAccessLevel();

            if ( self::$accessLevel !== 3 ) {
                self::$response['status'] = false;
                self::$response['message'] = 'You are not authorized to delete this widget.';
            } else {
                query("delete from pages_widgets_default
                   where page_id = ". self::$pageId ." and widget_id = ". self::$widgetId ." limit 1", __LINE__);
                self::$response['status'] = true;
                self::$response['message'] = 'Widget Deleted.';
            }
        }
        return self::$response;
    }
}