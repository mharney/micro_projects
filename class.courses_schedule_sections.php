<?php

class courses_schedule_sections extends widget {

    public static $widget_id = 2091;

    public function __construct() {
        return TRUE;
    }

    public static function get_css() {
        $html = "<style type='text/css'>
                    table.outer {
                        font-family: 'Trebuchet MS', Arial, Helvetica, sans-serif;
                        width: 100%;
                        border-collapse: collapse;
                        border-radius: 5px !important;
                        border: 1px solid #428adf;
                    }

                    td.outer, th.outer {
                        font-size: 1em;
                        border: 1px solid #428adf;
                        padding: 3px 7px 2px 7px;
                    }

                    th.outer {
                        font-size: 1.1em;
                        text-align: left;
                        padding-top: 5px;
                        padding-bottom: 4px;
                        background-color: #428adf;
                        color: #ffffff;
                    }

                    tr.outer td.outer {
                        color: #000000;
                        background-color: #FFFFFF;
                    }
                </style>";

        return $html;
    }

    public static function get_terms($scheduling_year_id) {
        global $School;

        # Get the terms
        $sql = "SELECT
                    dct.id
                    ,dct.abbr as name
                FROM school_cal_terms sct
                JOIN district_cal_terms dct ON dct.id = sct.district_cal_term_id
                WHERE sct.school_id = {$School->id}
                AND dct.year_id = {$scheduling_year_id}
                ORDER BY dct.sort_order";
        $terms = query($sql,__LINE__);

        return $terms;
    }

    public static function get_days() {
        global $School;

        # Get the days
        $sql = "SELECT
                    id
                    ,name
                FROM days
                WHERE school_id = {$School->id}
                AND is_scheduling = 1";
        $days = query($sql,__LINE__);

        return $days;
    }

    public static function get_periods() {
        global $School;

        # Get the periods
        $sql = "SELECT
                    id
                    ,abbr as name
                FROM periods
                WHERE school_id = {$School->id}
                AND is_scheduling = 1";
        $periods = query($sql,__LINE__);

        return $periods;
    }

    public static function get_courses_below_minimum($scheduling_year_id) {
        global $School;

        $sql = "SELECT
                    c.id
                    ,c.course_no
                    ,c.name
                    ,COUNT(cr.id) as no_requests
                    ,c.section_min
                    ,c.max_enrollment
                    ,CEIL(COUNT(cr.id) / c.max_enrollment) as no_sections_suggested
                FROM courses c
                LEFT JOIN courses_requests cr ON cr.course_id = c.id
                WHERE c.dis_cal_yr_id = {$scheduling_year_id}
                AND cr.school_id = $School->id
                AND c.school_id = $School->id
                GROUP BY c.id
                HAVING no_requests < c.section_min
                ORDER BY c.name";
        $courses = query($sql,__LINE__);

        return $courses;
    }

    public static function courses_below_minimum($scheduling_year_id) {

        # Set up
        $title = "Courses with Too Few Requests";
        $classes = array('standard_widget', 'widget_student_list');
        $html = '';
        $access_level = 3;
        $html .= self::get_css();
        $all_requests = array();

        $html .= <<<HTML
                <div class='alert alert-warning'>
                    These courses had too few requests to be considered for scheduling.  If you would
                    like to schedule these courses, decrease the minimum required section enrollment.
                    <br><br>
                    If you do not plan to schedule these courses, you must delete the associated requests.
                    <br><br>
                    <a class='btn' id='delete_all_requests'>Delete All Below Requests</a>&nbsp;&nbsp;
                    <a class='btn' onclick='parent.closeIFrame();'>Close</a>
                </div>
HTML;

        $courses = self::get_courses_below_minimum($scheduling_year_id);

        if( !empty($courses) ) {
            foreach($courses as $course_id => $course) {
                $sql = "SELECT
                            cr.id
                            ,u.id as user_id
                            ,u.first_name
                            ,u.last_name
                        FROM courses_requests cr
                        JOIN users u ON u.id = cr.user_id
                        WHERE cr.course_id = $course_id";
                $requests = query($sql,__LINE__);

                if( !empty($requests) ) {
                    foreach($requests as $request_id => $request) {
                        $all_requests[] = $request['id'];
                    }
                }

                $courses[$course_id]['inner_data'] = $requests;
                $courses[$course_id]['inner_head'] = ['ID','First Name','Last Name','Actions'];
                $courses[$course_id]['inner_body'] = ['user_id','first_name','last_name','actions'=>["delete_requests_inner"]];
            }
        }

        $html .= "<table class='outer'>
                    <tr class='outer'>
                        <th class='outer'>Courses with Too Few Requests</th>
                    </tr>
                    <tr>
                        <td>";
        $head = ['is_expand','No.','Course','Min.','Max.','No. Requests'];
        $body = ['course_no','name','section_min','max_enrollment','no_requests'];
        $html .= standard_widget_table($access_level,self::$widget_id,$courses,__CLASS__,$head,$body,'requests');
        $html .= "</td></tr></table>";
        $encoded_requests = json_encode($all_requests);
        $html .= <<<HTML
                    <script>
                        $(function(){
                            var requests = $encoded_requests;

                            $('#delete_all_requests').on('click', function () {
                            console.log(requests);

                                $.postJSON({
                                    url: "/api.php",
                                    type: "post",
                                    data: {
                                        method: "tooFewDeleteAllRequests",
                                        content: {
                                            requests: requests
                                        }
                                    },
                                    success: function(data) {
                                        console.log('success :' + data);
                                    }
                                });
                            });
                        });
                    </script>
HTML;

        # Return
        return parent::header(__CLASS__, $title, $classes) . $html . parent::footer();
    }

    public static function get_courses($scheduling_year_id) {
        global $School;

        $sql = "SELECT
                    csbt.id as course_id
                    ,c.id as orig_course_id
                    ,c.section_min
                    ,c.max_enrollment
                    ,COUNT(DISTINCT cr.user_id) as no_requests
                    ,CEIL(COUNT(DISTINCT cr.user_id) / c.max_enrollment) as no_sections_suggested
                    ,COUNT(DISTINCT ss.id) as no_sections_created
                    ,csbt.term_no
                    ,csbt.no_terms
                FROM courses c
                JOIN courses_requests cr ON cr.course_id = c.id
                JOIN users u ON u.id = cr.user_id
                JOIN users_school us ON us.district_id = $School->district_id AND us.status_id = 9 AND us.user_id = u.id
                JOIN courses_split_by_term csbt ON csbt.course_id = c.id
                LEFT JOIN sections_scheduling ss ON ss.course_id = csbt.id
                WHERE c.dis_cal_yr_id = {$scheduling_year_id}
                AND cr.school_id = $School->id
                AND c.school_id = $School->id
                GROUP BY csbt.id
                HAVING no_requests >= c.section_min AND no_sections_created < no_sections_suggested
                ORDER BY c.name, csbt.term_no
                -- LIMIT 10
                ";
        $courses = query($sql,__LINE__);
        return $courses;
    }

    public static function create_sections($scheduling_year_id, $courses=array()) {
        global $User, $School;

        $html = <<<HTML
            <div class="well processing">
                Processing&nbsp;&nbsp;&nbsp;<img src="/images/ajax-loader.gif">
            </div>
HTML;
        echo $html;

        # Courses
        if( empty($courses) ) {
            $courses = self::get_courses($scheduling_year_id);
        }

        # Terms
        $terms = self::get_terms($scheduling_year_id);
        $no_terms_in_schedule = count($terms);

        //TODO: House assignments
//p($courses);
        if( !empty($courses) ) {
            foreach($courses as $course_id => $course) {

                # Get the possible teachers for this course
                $sql = "SELECT
                            cpt.teacher_id
                            ,fte.min_hours
                            ,fte.max_hours
                            ,COUNT(sst.id) as num_sections
                        FROM courses_possible_teachers cpt
                        JOIN courses_split_by_term csbt ON csbt.course_id = cpt.course_id
                        JOIN teacher_fte fte ON fte.teacher_id = cpt.teacher_id AND fte.is_full_year = 1 AND fte.year_id = {$scheduling_year_id}
                        LEFT JOIN sections_scheduling_teachers sst ON sst.teacher_id = cpt.teacher_id AND sst.year_id = {$scheduling_year_id}
                        WHERE csbt.id = {$course['course_id']}
                        GROUP BY cpt.teacher_id
                        HAVING num_sections < max_hours
                        ORDER BY num_sections DESC"; // Do this so that we pick the teachers with the least fulfilled FTE first
                $teachers = query($sql,__LINE__);
p($teachers);
                $teachers_deck = $teachers; // As in, load up a deck of cards, wherein the 'cards' are teachers; we are going to draw

                if( !empty($teachers_deck) ) {
                    for($i = $course['no_sections_created']; $i < $course['no_sections_suggested']; $i++) {

                        $section_num = $i + 1;

                        # If this is a subsequent part of a multi-part course, need to keep the same teacher as already selected for the previous part(s)
                        $res = query("SELECT course_id, no_terms FROM courses_split_by_term WHERE id = {$course['course_id']}",__LINE__);
                        $row = array_pop($res);
                        if( $row['no_terms'] > 1 ) {

                            # See if any other parts have been scheduled
                            $sql = "SELECT id
                                    FROM sections_scheduling
                                    WHERE course_id IN (SELECT id FROM courses_split_by_term WHERE course_id IN (SELECT course_id FROM courses_split_by_term WHERE id = {$course['course_id']}))
                                    AND section_num = $section_num";
p($sql);
                            $res = query($sql,__LINE__);
                            $row = array_pop($res);
                            $sec_sched_id = $row['id'];
p($sec_sched_id);
                            if( !empty($sec_sched_id) ) {
                                $sql = "SELECT teacher_id FROM sections_scheduling_teachers WHERE sec_sched_id = $sec_sched_id";
                                $res = query($sql,__LINE__);
                                $row = array_pop($res);
                                $other_part_teacher_id = $row['teacher_id'];
                            }
                            else {
                                $other_part_teacher_id = 0;
                            }
                        }
                        else {
                            $other_part_teacher_id = 0;
                        }
p($other_part_teacher_id);
                        $sql = "INSERT INTO sections_scheduling
                                (
                                  `course_id`,
                                  `school_id`,
                                  `district_id`,
                                  `year_id`,
                                  `section_num`,
                                  `created_by`
                                )
                                VALUES
                                (
                                  {$course['course_id']},
                                  {$School->id},
                                  {$School->district_id},
                                  {$scheduling_year_id},
                                  {$section_num},
                                  {$User->id}
                                )";
p($sql);
                        $id = save($sql,__LINE__);

                        # Section to term assignments
                        if( !empty($course['term_id']) AND $course['term_id'] != 0 ) {
                            $term_id = $course['term_id'];
                        }
                        else {
                            # Get the possible terms for this section and pick the term with the fewest sections already scheduled to it
                            # Query is complex because it must return 0 for a term if no sections have been scheduled to it (instead of null)
                            $sql = "SELECT
                                        dct.id as term_id
                                        ,COUNT(sst.id) as num_sections
                                    FROM school_cal_terms sct
                                    JOIN district_cal_terms dct ON dct.id = sct.district_cal_term_id
                                    LEFT JOIN sections_scheduling_terms sst ON sst.term_id = dct.id
                                    WHERE sct.school_id = {$School->id}
                                    AND dct.year_id = {$scheduling_year_id}
                                    AND dct.id IN
                                    (
                                        -- This subquery gets the possible terms the course might meet in based on
                                        -- the terms at the school and the position of the term in the year-order (e.g. term 1 of 3)
                                        -- and the order of this course piece after splitting
                                        SELECT
                                            dct.id
                                        FROM courses_split_by_term csbt
                                        JOIN courses_possible_terms cpt ON cpt.course_id = csbt.course_id
                                        JOIN district_cal_terms dct ON dct.id = cpt.term_id
                                            AND dct.sort_order >= csbt.term_no
                                            AND dct.year_id = {$scheduling_year_id}
                                            AND (csbt.no_terms - csbt.term_no) <= ({$no_terms_in_schedule} - dct.sort_order)
                                        JOIN school_cal_terms sct ON sct.district_cal_term_id = dct.id
                                            AND sct.school_id = {$School->id}
                                        WHERE csbt.id = {$course['course_id']}
                                    )
                                    GROUP BY dct.id
                                    ORDER BY num_sections DESC";
                            $res = query($sql,__LINE__);
                            $row = array_pop($res);
                            $term_id = $row['term_id'];
                        }

                        $sql = "INSERT INTO sections_scheduling_terms
                                (
                                  `sec_sched_id`,
                                  `term_id`,
                                  `school_id`,
                                  `year_id`
                                )
                                VALUES
                                (
                                  {$id},
                                  {$term_id},
                                  {$School->id},
                                  {$scheduling_year_id}
                                )";
                        save($sql,__LINE__);

                        # Section to teacher assignments
                        $is_teacher_assigned = 0;
                        $num_teachers_to_try = empty($course['teacher_id']) ? count($teachers_deck) : 1;

                        for( $j = 0; $j < $num_teachers_to_try; $j++ ) {
                            if($is_teacher_assigned == 0) {
                                if($other_part_teacher_id == 0) {
                                    if( empty($course['teacher_id']) ) {

                                        # Pick a teacher out of the deck
                                        $teacher = array_pop($teachers_deck);
                                        $teacher_id = $teacher['teacher_id'];
                                    }
                                    else {
                                        $teacher_id = $course['teacher_id'];
                                    }
                                }
                                else {
                                    $teacher_id = $other_part_teacher_id;
                                }
p("teacher_id");
p($teacher_id);
                                if( !empty($teacher_id) ) {

                                    # See if the teacher still has room to teach the section in the term selected
                                    # Get the teacher's max FTE in the term selected
                                    $sql = "SELECT
                                            fte.term_id
                                            ,fte.max_hours
                                        FROM teacher_fte fte
                                        WHERE fte.teacher_id = $teacher_id
                                        AND fte.term_id = $term_id
                                        ORDER BY fte.term_id";
                                    $res = query($sql,__LINE__);
                                    $row = array_pop($res);
                                    $max_hours = $row['max_hours'];
                                    p($max_hours);
                                    # Get the number of sections the teacher has already been assigned to teach in this term
                                    $sql = "SELECT
                                            COUNT(sst.id) as num_sections
                                        FROM sections_scheduling_teachers sst
                                        JOIN sections_scheduling_terms sstm ON sstm.sec_sched_id = sst.sec_sched_id
                                        WHERE sst.teacher_id = $teacher_id
                                        AND sstm.term_id = $term_id
                                        ORDER BY sstm.term_id";
                                    $res = query($sql,__LINE__);
                                    $row = array_pop($res);
                                    $num_sections_assigned = $row['num_sections'];
                                    p($num_sections_assigned);

                                    # Make sure that even if the FTE max works out, the teacher has some possible time slots in this term
                                    $sql = "SELECT id FROM teacher_timeslots WHERE teacher_id = $teacher_id AND term_id = $term_id";
                                    p($sql);
                                    $teacher_time_slots = query($sql,__LINE__);

                                    $fte_check = ($max_hours <= $num_sections_assigned OR empty($teacher_time_slots)) ? 0 : 1;
                                    p($fte_check);
                                    if( $fte_check > 0 ) {
                                        $sql = "INSERT INTO sections_scheduling_teachers
                                            (
                                              `sec_sched_id`,
                                              `teacher_id`,
                                              `school_id`,
                                              `year_id`
                                            )
                                            VALUES
                                            (
                                              {$id},
                                              {$teacher_id},
                                              {$School->id},
                                              {$scheduling_year_id}
                                            )";
                                        p($sql);
                                        save($sql,__LINE__);
                                        $is_teacher_assigned = 1;
                                        p($is_teacher_assigned);
                                    }
                                }
                            }
                        }
p($is_teacher_assigned);
                        if($is_teacher_assigned == 0) {
                            // Since we were unable to assign a teacher, scrap the section
                            query("DELETE FROM sections_scheduling WHERE id = $id",__LINE__);
                            query("DELETE FROM sections_scheduling_terms WHERE sec_sched_id = $id",__LINE__);
                        }

                        # Reload the deck if necessary as we continue to schedule more sections of this course
                        if( empty($teachers_deck) ) {
                            $teachers_deck = $teachers;
                        }
                    }
                }
            }
        }

        # When finished scheduling all the courses, delete any partial courses
        # Get all the courses_split_by_term that are multi-part
        $sql = "SELECT id, course_id FROM courses_split_by_term WHERE no_terms > 1";
        $multi_part_courses = query($sql,__LINE__);

        if( !empty($multi_part_courses) ) {
            foreach($multi_part_courses as $course_id => $course) {

                # Get sections of this course part
                $sql = "SELECT id, section_num FROM sections_scheduling WHERE course_id = {$course['id']}";
                $sections = query($sql,__LINE__);

                # Get the other parts
                $sql = "SELECT id FROM courses_split_by_term WHERE course_id = {$course['course_id']} AND id != {$course['id']}";
                $other_parts = query($sql,__LINE__);


                # Check if there is a full course or not
                if( !empty($sections) ) {
                    foreach($sections as $section_id => $section) {
                        if( !empty($other_parts) ) {
                            foreach($other_parts as $other_part) {
                                $sql = "SELECT * FROM sections_scheduling WHERE course_id = {$other_part['id']} AND section_num = {$section['section_num']}";
                                $res = query($sql,__LINE__);

                                # If it is ever empty, that means we are missing a part, so delete all related pieces
                                if( empty($res) ) {
                                    query("DELETE FROM sections_scheduling WHERE id = {$section['id']}",__LINE__);
                                    query("DELETE FROM sections_scheduling_terms WHERE sec_sched_id = {$section['id']}",__LINE__);
                                    query("DELETE FROM sections_scheduling_teachers WHERE sec_sched_id = {$section['id']}",__LINE__);
                                }
                            }
                        }
                    }
                }
            }
        } # End checking for multi-part course orphans

        return widget::$save_success_close;
    }

    public static function check_fix_fte($scheduling_year_id) {
        global $School;

        # Hack - do this 4 times
        for($i = 0; $i < 4; $i++) {
            # After we have gone through and created the number of suggested sections, check FTE
            $res = self::get_assigned_fte($scheduling_year_id, 1, 0, 1);
p($res);
            if( !empty($res) ) {
                foreach($res as $fte_id => $fte) {
                    $courses = array();

                    # Find out how much the fte deficit is
                    $deficit = $fte['min_hours'] - $fte['fte'];

                    $teacher_id = $fte['teacher_id'];
p($teacher_id);
p($deficit);
                    $term_fte = self::get_assigned_fte($scheduling_year_id, 0, $teacher_id, 1);
p($term_fte);
                    $no_terms_in_deficit = count($term_fte);

                    if($no_terms_in_deficit > 1) {
                        $term_id = 0;
                        $term_fte_flat = array_map(function($item) { return $item['term_id']; }, $term_fte);
                        $term_join = empty($term_fte) ? "" : " JOIN courses_possible_terms cptm ON cptm.course_id = c.id AND cptm.term_id IN (".implode(',',$term_fte_flat).") ";
                        $term_order = "";
                    }
                    else {
                        $row = array_pop($term_fte);
                        $term_id = $row['term_id'];
                        $term_join = empty($term_id) ? "" : " JOIN courses_possible_terms cptm ON cptm.course_id = c.id AND cptm.term_id = $term_id ";
                        $term_order = empty($row['term_order']) ? "" : " AND CASE WHEN csbt.no_terms > 1 THEN csbt.term_no = {$row['term_order']} ELSE 1 END";
                    }

                    # Get a course that would bump up this teacher's FTE and where it makes sense to offer another section
                    $sql = "SELECT
                                csbt.id as course_id
                                ,c.id as orig_course_id
                                ,c.section_min
                                ,c.max_enrollment
                                ,COUNT(DISTINCT cr.user_id) as no_requests
                                ,CEIL(COUNT(DISTINCT cr.user_id) / c.max_enrollment) as no_sections_suggested
                                ,COUNT(DISTINCT ss.id) as no_sections_created
                                ,csbt.term_no
                                ,csbt.no_terms
                                ,ROUND(COUNT(DISTINCT cr.user_id) / COUNT(DISTINCT ss.id)) as avg_section_size
                                ,ROUND(COUNT(DISTINCT cr.user_id) / (COUNT(DISTINCT ss.id)+1)) as new_avg_section_size
                                ,ROUND(COUNT(DISTINCT cr.user_id) / (COUNT(DISTINCT ss.id)+1)) - c.section_min as students_above_min
                            FROM courses c
                            JOIN courses_possible_teachers cpt ON cpt.course_id = c.id
                            $term_join
                            JOIN courses_requests cr ON cr.course_id = c.id
                            JOIN users u ON u.id = cr.user_id
                            JOIN users_school us ON us.district_id = $School->district_id AND us.status_id = 9 AND us.user_id = u.id
                            JOIN courses_split_by_term csbt ON csbt.course_id = c.id AND csbt.no_terms <= $deficit -- Exclude multi-part courses if inadequate availability
                              $term_order
                            LEFT JOIN sections_scheduling ss ON ss.course_id = csbt.id
                            WHERE c.dis_cal_yr_id = $scheduling_year_id
                            AND cr.school_id = $School->id
                            AND c.school_id = $School->id
                            AND cpt.teacher_id = $teacher_id
                            GROUP BY csbt.id
                            HAVING no_requests >= c.section_min AND students_above_min >= 0
                            ORDER BY students_above_min ASC";
p($sql);
                    $result = query($sql,__LINE__);
p($result);
                    if( !empty($result) ) {

                        $row = array_pop($result);
                        $new_no_sections_suggested = $row['no_sections_created'] + 1;
                        $courses[] = array('course_id'=>$row['course_id'],
                                           'no_sections_created'=>$row['no_sections_created'],
                                           'no_sections_suggested'=>$new_no_sections_suggested,
                                           'teacher_id' => $teacher_id,
                                           'term_id' => $term_id);
p($courses);
                        foreach($result as $key => $value) {
                            // As we cannot separate split courses, we need to create an additional section of all parts
                            if($value['orig_course_id'] == $row['orig_course_id']) {
                                $new_no_sections_suggested = $value['no_sections_created'] + 1;
                                $courses[] = array('course_id'=>$value['course_id'],
                                                   'no_sections_created'=>$value['no_sections_created'],
                                                   'no_sections_suggested'=>$new_no_sections_suggested,
                                                   'teacher_id' => $teacher_id);
                            }
                        }
                    }
p($courses);
                    if( !empty($courses) ) {
                        self::create_sections($scheduling_year_id, $courses);
                    }
                }
            }
        }
        return widget::$save_success_close;
    }

    public static function delete_sections($params) {
        global $School;

        query("DELETE FROM sections_scheduling WHERE school_id = {$School->id} AND year_id = {$params['scheduling_year_id']}",__LINE__);
        query("DELETE FROM sections_scheduling_terms WHERE school_id = {$School->id} AND year_id = {$params['scheduling_year_id']}",__LINE__);
        query("DELETE FROM sections_scheduling_teachers WHERE school_id = {$School->id} AND year_id = {$params['scheduling_year_id']}",__LINE__);

        return widget::$save_success_close;
    }

    public static function get_assigned_fte($scheduling_year_id, $is_full_year, $user_id = 0, $is_deficit = 0) {
        global $School;

        $user_sql = $user_id == 0 ? "" : " AND fte.teacher_id = $user_id ";

        $sql = "SELECT
                    DISTINCT fte.teacher_id
                    ,concat(u.first_name,' ',u.last_name) as teacher_name
                    ,fte.term_id
                    ,CASE WHEN fte.term_id IS NULL THEN 'Full Yr' ELSE dct.abbr END as term
                    ,fte.min_hours
                    ,fte.max_hours
                    ,dct.sort_order as term_order
                FROM teacher_fte fte
                JOIN users u ON u.id = fte.teacher_id
                LEFT JOIN district_cal_terms dct ON dct.id = fte.term_id
                WHERE fte.school_id = {$School->id}
                AND fte.year_id = {$scheduling_year_id}
                AND fte.is_full_year = {$is_full_year}
                AND fte.min_hours > 0
                $user_sql
                GROUP BY  fte.term_id, u.id
                ORDER BY u.last_name, u.first_name, fte.term_id";
        $fte = query($sql,__LINE__);

        # Get the number of sections the teacher is teaching in each term
        if( !empty($fte) ) {
            foreach($fte as $key => $value) {

                $term_sql = $is_full_year == 1 ? "" : " AND sec_sched_terms.term_id = {$value['term_id']} ";

                $sql = "SELECT COUNT(sec_sched_teach.id) as fte
                        FROM sections_scheduling_teachers sec_sched_teach
                        JOIN sections_scheduling_terms sec_sched_terms ON sec_sched_terms.sec_sched_id = sec_sched_teach.sec_sched_id $term_sql
                        WHERE sec_sched_teach.teacher_id = {$value['teacher_id']}
                        AND sec_sched_teach.year_id = {$scheduling_year_id}
                        AND sec_sched_teach.school_id = {$School->id}";
                $res = query($sql,__LINE__);
                $row = array_pop($res);
                $fte[$key]['fte'] = $row['fte'];

                if($is_deficit == 1 AND ($fte[$key]['fte'] >= $fte[$key]['min_hours']) ) {
                    unset($fte[$key]);
                }
            }
        }
        return $fte;
    }

    public static function view_assigned_fte($scheduling_year_id) {

        # Set up
        $title = "Assigned FTE";
        $classes = array('standard_widget', 'widget_student_list');
        $html = "";
        $access_level = 3;

        $fte = self::get_assigned_fte($scheduling_year_id, 1);

        if( !empty($fte) ) {
            foreach($fte as $key => $value) {
                $fte[$key]['alert'] = ($fte[$key]['min_hours'] <= $fte[$key]['fte'] AND $fte[$key]['max_hours'] >= $fte[$key]['fte']) ?
                                    "--" :
                                    "<i style='color: red; font-size:14px !important;' class='fa fa-exclamation-triangle'></i>";

                $inner_fte = self::get_assigned_fte($scheduling_year_id, 0, $value['teacher_id']);

                foreach($inner_fte as $k => $v) {
                    $inner_fte[$k]['alert'] = ($inner_fte[$k]['min_hours'] <= $inner_fte[$k]['fte'] AND $inner_fte[$k]['max_hours'] >= $inner_fte[$k]['fte']) ?
                        "--" :
                        "<i style='color: red; font-size:14px !important;' class='fa fa-exclamation-triangle'></i>";
                }

                $fte[$key]['inner_data'] = $inner_fte;
                $fte[$key]['inner_head'] = ['Term','Target Min.','Target Max.','Assigned FTE','Alert'];
                $fte[$key]['inner_body'] = ['term','min_hours','max_hours','fte','alert'];
            }
        }

        $html .= "<a class='btn' onclick='parent.closeIFrame();'>Close</a><br><br>";
        $head = ['is_expand','ID', 'Teacher','Term','Target Min.','Target Max.','Assigned FTE','Alert'];
        $body = ['teacher_id', 'teacher_name','term','min_hours','max_hours','fte','alert'];
        $html .= standard_widget_table($access_level,self::$widget_id,$fte,__CLASS__,$head,$body,'fte');

        # Return
        return parent::header(__CLASS__, $title, $classes) . $html . parent::footer();
    }


    public static function check_violations($course_id, $scheduling_year_id) {

        $violations = 0;

        # Are there possible teachers for this course?
        $teachers = self::get_teachers_with_rooms_and_time_slots($course_id, $scheduling_year_id);
        $violations += empty($teachers['teachers']) ? 1 : 0;

        # Are there possible rooms for this course?
        $rooms = self::get_course_rooms($course_id);
        $rooms_flat = array_map(function($item) { return $item['id']; }, $rooms);
        $violations += empty($rooms) ? 1 : 0;

        # Do teachers and rooms intersect?
        $teacher_rooms_flat = self::get_teacher_rooms($teachers);
        $int = array_intersect($rooms_flat,$teacher_rooms_flat);
        $sql = "SELECT r.id, r.num, r.max as capacity FROM rooms r WHERE id IN (".implode(',',$int).")";
        $room_intersection = query($sql,__LINE__);
        $violations += empty($room_intersection) ? 1 : 0;

        # Are there possible time slots for this course?
        $time_slots = self::get_course_time_slots($course_id, $scheduling_year_id);
        $course_times_flat = array_map(function($item) { return $item['time_slot_id']; }, $time_slots);
        $violations += empty($time_slots) ? 1 : 0;

        # Do course and teacher time slots intersect?
        $all_teacher_times = $teachers['teacher_times'];
        $time_slot_intersection = self::get_course_teacher_time_slot_intersection($course_times_flat, $all_teacher_times, $scheduling_year_id);
        $violations += empty($time_slot_intersection) ? 1 : 0;

        /* TODO Other violations include:
        -- Are there courses with requests but no sections created?
        -- Are there courses with fewer than the recommended number of sections created so all requests cannot be filled?
        -- Do all rooms have non-zero maximum capacity indicated?
        -- Do all courses have non-zero minimum and maximum section sizes entered?
        -- Do all students have requests totaling at least 15 FTE and not more than than 15 FTE?
        -- Are there FTE violations either full-year or in individual terms? (In current FTE view, term violations
            don't show until expansion if no full-year violation; need to fix this)
        */

        return $violations;
    }



    public static function get_teachers_with_rooms_and_time_slots($course_id, $scheduling_year_id) {
        global $School;

        $all_teacher_times = array();

        $sql = "SELECT
                    u.id
                    ,u.first_name
                    ,u.last_name
                    ,GROUP_CONCAT(DISTINCT r.num ORDER BY r.num SEPARATOR '<br>') as rooms
                FROM courses_possible_teachers cpt
                JOIN users u ON u.id = cpt.teacher_id
                LEFT JOIN teacher_rooms tr ON tr.teacher_id = u.id
                LEFT JOIN rooms r ON r.id = tr.room_id
                WHERE cpt.course_id = {$course_id}
                GROUP BY u.id
                ORDER BY u.last_name";
        $teachers = query($sql,__LINE__);

        if( !empty($teachers) ) {
            foreach($teachers as $teacher_id => $teacher) {
                $sql = "SELECT
                            concat(dct.id,'_',d.id,'-',p.id) as time_slot_id
                            ,dct.term_name
                            ,dct.abbr as term_abbr
                            ,d.name as day_name
                            ,p.abbr as period_abbr
                        FROM teacher_timeslots tt
                        JOIN district_cal_terms dct ON dct.id = tt.term_id
                        JOIN days d ON d.id = tt.day_id
                        JOIN periods p ON p.id = tt.period_id
                        WHERE dct.year_id = {$scheduling_year_id}
                        AND tt.teacher_id = {$teacher['id']}
                        AND tt.school_id = {$School->id}
                        ORDER BY dct.sort_order, d.sort_order, p.sort_order";
                $teacher_time_slots = query($sql,__LINE__);
                $teacher_times_flat = array_map(function($item) { return $item['time_slot_id']; }, $teacher_time_slots);
                $all_teacher_times = array_merge($all_teacher_times,$teacher_times_flat);

                $teachers[$teacher_id]['inner_data'] = $teacher_time_slots;
                $teachers[$teacher_id]['inner_head'] = ['Term','Day','Period'];
                $teachers[$teacher_id]['inner_body'] = ['term_abbr','day_name','period_abbr'];
            }
        }
        $all_teacher_times = array_unique($all_teacher_times);

        return array('teachers'=>$teachers,'teacher_times'=>$all_teacher_times);
    }

    public static function get_teacher_rooms($teachers) {

        $teachers_flat = array_map(function($item) { return $item['id']; }, $teachers['teachers']);
        $teachers_imploded = implode(',',$teachers_flat);

        $sql = "SELECT tr.room_id as id FROM teacher_rooms tr WHERE teacher_id IN ($teachers_imploded)";
        $teacher_rooms = query($sql,__LINE__);
        $teacher_rooms_flat = array_map(function($item) { return $item['id']; }, $teacher_rooms);

        return $teacher_rooms_flat;
    }

    public static function get_course_rooms($course_id) {

        $sql = "SELECT
                    r.id
                    ,r.num
                    ,r.max as capacity
                FROM courses_possible_rooms cpr
                JOIN rooms r ON r.id = cpr.room_id
                WHERE cpr.course_id = {$course_id}
                ORDER BY r.num";
        $rooms = query($sql,__LINE__);

        return $rooms;
    }

    public static function get_course_time_slots($course_id, $scheduling_year_id) {

        $sql = "SELECT
                    concat(dct.id,'_',d.id,'-',p.id) as time_slot_id
                    ,dct.id as term_id
                    ,dct.term_name
                    ,dct.abbr as term_abbr
                    ,d.id as day_id
                    ,d.name as day_name
                    ,p.id as period_id
                    ,p.abbr as period_abbr
                FROM courses_possible_timeslots cpt
                JOIN district_cal_terms dct ON dct.id = cpt.term_id
                JOIN days d ON d.id = cpt.day_id
                JOIN periods p ON p.id = cpt.period_id
                WHERE dct.year_id = {$scheduling_year_id}
                AND cpt.course_id = {$course_id}
                ORDER BY dct.sort_order, d.sort_order, p.sort_order";

        $time_slots = query($sql,__LINE__);
        return $time_slots;
    }

    public static function get_course_teacher_time_slot_intersection($course_times_flat, $all_teacher_times, $scheduling_year_id) {

        $time_slot_int = array_intersect($course_times_flat,$all_teacher_times);

        $time_slot_intersection = array();

        $terms = self::get_terms($scheduling_year_id);
        $days = self::get_days();
        $periods = self::get_periods();

        if( !empty($time_slot_int) ) {
            foreach($time_slot_int as $time_slot) {
                $stripos_underscore = stripos($time_slot, '_');
                $stripos_hyphen = stripos($time_slot, '-');

                $term_id = substr($time_slot,0,$stripos_underscore);
                $day_id = substr($time_slot,$stripos_underscore+1,$stripos_hyphen-($stripos_underscore+1));
                $period_id = substr($time_slot,$stripos_hyphen+1);

                $time_slot_intersection[] = array('term_id'=>$term_id,
                        'day_id'=>$day_id,
                        'period_id'=>$period_id,
                        'term_name'=>$terms[$term_id]['name'],
                        'day_name'=>$days[$day_id]['name'],
                        'period_name'=>$periods[$period_id]['name']);
            }
        }
        return $time_slot_intersection;
    }


    public static function update($params) {
        $params = escape_all($params);

        query("DELETE FROM courses_possible_teachers WHERE course_id = '{$params['id']}'",__LINE__);
        query("DELETE FROM courses_possible_rooms WHERE course_id = '{$params['id']}'",__LINE__);
        query("DELETE FROM courses_possible_terms WHERE course_id = '{$params['id']}'",__LINE__);
        query("DELETE FROM courses_possible_houses WHERE course_id = '{$params['id']}'",__LINE__);

        $sql = "UPDATE `tsc`.`courses`
                SET
                    term_len =  '{$params['term_len_select']}',
                    section_min = '{$params['min_enrollment']}',
                    max_enrollment = '{$params['max_enrollment']}'
                WHERE id = '{$params['id']}'";
        save($sql, __LINE__);

        if( ! empty($params['teacher_ids']) ) {
            foreach($params['teacher_ids'] as $tid) {

                $tid = (int)$tid;
                $sql = "INSERT INTO `tsc`.`courses_possible_teachers`
                        (
                            `course_id`,
                            `teacher_id`
                        )
                        VALUES
                        (
                            '{$params['id']}',
                            '{$tid}'
                        )";
                save($sql,__LINE__);
            }
        }

        if( ! empty($params['room_ids']) ) {
            foreach($params['room_ids'] as $rid ) {

                $rid = (int)$rid;
                $sql = "INSERT INTO `tsc`.`courses_possible_rooms`
                        (
                            `course_id`,
                            `room_id`
                        )
                        VALUES
                        (
                            '{$params['id']}',
                            '{$rid}'
                        )";
                save($sql,__LINE__);
            }
        }

        if( ! empty($params['house_ids']) ) {
            foreach($params['house_ids'] as $hid) {

                $hid = (int)$hid;
                $sql = "INSERT INTO `tsc`.`courses_possible_houses`
                        (
                            `course_id`,
                            `house_id`
                        )
                        VALUES
                        (
                            '{$params['id']}',
                            '{$hid}'
                        )";
                save($sql,__LINE__);
            }
        }

        if( ! empty($params['term_ids']) ) {
            foreach($params['term_ids'] as $tmid) {

                $tmid=(int)$tmid;
                $sql = "INSERT INTO `tsc`.`courses_possible_terms`
                        (
                            `course_id`,
                            `term_id`
                        )
                        VALUES
                        (
                            '{$params['id']}',
                            '{$tmid}'
                        )";
                save($sql,__LINE__);
            }
        }

        # Save timetable
        save("delete from courses_possible_timeslots where course_id = '{$params['id']}'", __LINE__);

        $tt = @$_POST['time_table'];
        if( empty($tt) || ! is_array($tt) ) {
            $tt = [];
        }

        foreach($tt as $t) {
            $t = explode('x', $t);
            $term_id = $t[0];
            $period_id = $t[1];
            $day_id = $t[2];
            save("insert into courses_possible_timeslots (`course_id`,`term_id`,`day_id`,`period_id`) values ('{$params['id']}','{$term_id}','{$day_id}','{$period_id}') ", __LINE__);
        }
        return TRUE;
    }

    public static function edit_courses($params) {
        global $School;

        # Set up
        $title = "Edit Course Settings";
        $classes = array('standard_widget', 'widget_student_list');
        $html = '';

        if( !empty($_POST['modal_save']) ) {
            self::update($_POST);
            return widget::$save_success_close;
        }

        # Get course info
        $res = query("SELECT c.* FROM courses c WHERE c.id = '{$params['id']}'",__LINE__);
        $course = array_pop($res);
        $year_id = $course['dis_cal_yr_id'];
        $tt_data = array();

        # Buttons
        $buttons = "<button class='btn btn-success'>
                        Update &nbsp;<i class='fa fa-arrow-circle-right'></i>
                    </button>&nbsp;
                    <input type='hidden' name='id' value='{$params['id']}' />
                    <input type='hidden' name='modal_save' value='1'>
                    <a class='btn' onclick='parent.closeIFrame();'>Cancel</a>";

        $selected_teachers = query("SELECT teacher_id as id FROM courses_possible_teachers WHERE course_id = '{$params['id']}'",__LINE__);
        $selected_teachers = @array_keys($selected_teachers);

        $selected_rooms = query("SELECT room_id as id FROM courses_possible_rooms WHERE course_id = '{$params['id']}'",__LINE__);
        $selected_rooms = @array_keys($selected_rooms);

        $selected_houses = query("SELECT house_id as id FROM courses_possible_houses WHERE course_id = '{$params['id']}'",__LINE__);
        $selected_houses = @array_keys($selected_houses);

        $selected_terms = query("SELECT term_id as id FROM courses_possible_terms WHERE course_id = '{$params['id']}'",__LINE__);
        $selected_terms = @array_keys($selected_terms);

        # Term length
        $term_lengths = array(1 => array('id'=>1, 'name' => 'Full Year'),
            2 => array('id'=>1, 'name' => 'Semester'),
            3 => array('id'=>1, 'name' => 'Trimester'),
            4 => array('id'=>1, 'name' => 'Quarter'),
            5 => array('id'=>1, 'name' => 'Fifth'));

        $term_lengths_select = get_multiselect('term_len',$term_lengths, '', 'span3', [@$course['term_len']],
            ['rule' => 'required,Term length is required']);

        # Min enrollment
        $min_enrollment = get_number([
            'name'=>'min_enrollment',
            'value'=> @$course['section_min'],
            'placeholder'=>'15',
            'class'=>'span1 required',
            'rule'=>'required,Min enrollment required'
        ]);

        # Max enrollment
        $max_enrollment = get_number([
            'name'=>'max_enrollment',
            'value'=> @$course['max_enrollment'],
            'placeholder'=>'35',
            'class'=>'span1 required',
            'rule'=>'required,Max enrollment required'
        ]);

        # Possible Teachers
        $sql = "SELECT u.id, concat(u.first_name,' ',u.last_name) as name
                FROM users_school us
                JOIN users u on u.id = us.user_id
                WHERE us.school_id = $School->id
                AND us.role_id = 2";
        $teacher_options = query($sql,__LINE__);
        $teachers_select = get_multiselect('teacher_ids[]', $teacher_options, 'multiple title =\'Teachers:\'', 'span4', @$selected_teachers, '');

        # Possible Houses
        $house_options = query("SELECT g.id, concat(gl.description,': ',g.name) as name
                                FROM groups g
                                JOIN grade_levels gl ON gl.id = g.grade_level_id
                                WHERE g.school_id = $School->id
                                AND g.is_house = 1
                                ORDER BY gl.sort_order, g.name",__LINE__);
        $houses_select = get_multiselect('house_ids[]',$house_options,' multiple title =\'Houses:\'', 'span4', @$selected_houses, '');

        # Possible Rooms
        $room_options = query("SELECT r.id, concat(b.name,': ',r.num) as name
                               FROM rooms r
                               JOIN buildings b on r.building_id = b.id
                               JOIN buildings_school bs on bs.building_id = r.building_id
                               WHERE bs.school_id = $School->id",__LINE__);
        $rooms_select = get_multiselect('room_ids[]',$room_options,' multiple title =\'Rooms:\'', 'span4', @$selected_rooms, '');

        # Possible Terms
        $term_options = query("SELECT dt.id, dt.term_name name
                                FROM district_cal_terms dt
                                JOIN school_cal_terms st on st.district_cal_term_id = dt.id
                                WHERE dt.district_id = $School->district_id
                                AND st.school_id = $School->id
                                AND dt.year_id = $year_id",__LINE__);
        $terms_select = get_multiselect('term_ids[]',$term_options,' multiple title =\'Terms:\'', 'span4', @$selected_terms, ['id'=>'terms_select']);

        $timetables = query("select * from courses_possible_timeslots where course_id = '{$course['id']}'", __LINE__);
        if( ! empty($timetables) ){
            foreach($timetables as $timetable_id => $timetable){
                $tt_data[] = array(
                    $timetables[$timetable_id]['term_id'],
                    $timetables[$timetable_id]['period_id'],
                    $timetables[$timetable_id]['day_id']
                );
            }
        }
        $time_table = get_timetable([
            'name'=>'time_table',
            'values'=>$tt_data,
            'terms_select_element_id'=>'terms_select'
        ]);

        $html .= <<<HTML
        <form action='' method='post' name='course_district_info_form' id="course_district_info_form">
            <table class='table-form'>
                <tr>
                    <th valign="top">Term Length</th>
                    <td valign="top">$term_lengths_select</td>
                </tr>
                <tr>
                    <th valign="top">Min. Section Size</th>
                    <td valign="top">$min_enrollment</td>
                </tr>
                <tr>
                    <th valign="top">Max. Section Size</th>
                    <td valign="top">$max_enrollment</td>
                </tr>
                <tr id="teacher_ids">
                    <th valign="top">Possible Teachers</th>
                    <td valign="top">$teachers_select</td>
                </tr>
                <tr>
                    <th valign="top">Possible Rooms</th>
                    <td valign="top">$rooms_select</td>
                </tr>
                <tr>
                    <th valign="top">Possible Houses</th>
                    <td valign="top">$houses_select</td>
                </tr>
                <tr>
                    <th valign="top">Possible Terms</th>
                    <td valign="top">$terms_select</td>
                </tr>
                <tr><td>&nbsp;</td></tr>
                <tr>
                    <th valign="top">Time Tables</th>
                    <td valign="top">$time_table</td>
                </tr>
            </table>
                <br><br><br>
                $buttons
            </form>
            <script>
                $('#course_district_info_form').validationEngine({
                    prettySelect:true,
                    useSuffix: '_chosen'
                });
                $(document).ready(function() {
                    $('#teacher_ids select').on('change', function(){

                        var teacher_ids = $(this).find('option:selected').map(function () { return this.value; }).get();

                        // Get rooms based on teacher(s) selected
                        $.ajax({
                            url: '/ajax_get.php?action=get_rooms_from_teacher_ids',
                            method: 'post',
                            dataType: 'json',
                            data: {
                                teacher_ids : teacher_ids
                            },
                            // Set the rooms based on data retrieved
                            success : function(select) {
                                //console.log($.map(select, function (item) { return item.room_id; }));
                                $( "select[name='room_ids[]']" ).val($.map(select, function (item) { return item.room_id; })).trigger("chosen:updated");
                            }
                        });
                    });
                });
            </script>
HTML;

        # Return
        return parent::header(__CLASS__, $title, $classes) . $html . parent::footer();
    }

    public static function view_details_courses($params) {
        global $School;

        $sql = "SELECT
                    c.id
                    ,c.course_no
                    ,c.short_name
                    ,c.name
                    ,COUNT(cr.id) as no_requests
                    ,c.section_min
                    ,c.max_enrollment
                    ,CEIL(COUNT(cr.id) / c.max_enrollment) as no_sections_suggested
                    ,c.dis_cal_yr_id as year_id
                FROM courses c
                JOIN courses_requests cr ON cr.course_id = c.id
                JOIN users u ON u.id = cr.user_id
                JOIN users_school us ON us.district_id = $School->district_id AND us.status_id = 9 AND us.user_id = u.id
                WHERE c.id = {$params['id']}";
        $res = query($sql,__LINE__);
        $row = array_pop($res);
        $scheduling_year_id = $row['year_id'];
        $course_id = $params['id'];

        # Set up
        $title = "Course Details: {$row['short_name']}";
        $classes = array('standard_widget', 'widget_student_list');
        $html = '';
        $access_level = 3;

        $html .= self::get_css();

        $html .= <<<HTML
                <a class='btn' onclick='parent.closeIFrame();'>Close</a><br><br>
                <div class='alert alert-warning'>
                    <table class='table-form'>
                        <tr>
                            <th>Section Min.:</th><td>{$row['section_min']}</td>
                            <td width='50px'>&nbsp;</td>
                            <th>Section Max.:</th><td>{$row['max_enrollment']}</td>
                        </tr>
                        <tr>
                            <th>No. Requests:</th><td>{$row['no_requests']}</td>
                            <td>&nbsp;</td>
                            <th>Suggested No. Sections:</th><td>{$row['no_sections_suggested']}</td>
                        </tr>
                    </table>
                </div>
HTML;

        $teachers = self::get_teachers_with_rooms_and_time_slots($course_id, $scheduling_year_id);
        $all_teacher_times = $teachers['teacher_times'];

        $style_th = empty($teachers['teachers']) ? "style='background-color:#FF0000; border:1px #FF0000 solid'" : "";
        $style_td = empty($teachers['teachers']) ? "style='border:1px #FF0000 solid'" : "";

        $html .= "<table class='outer'>
                    <tr class='outer'>
                        <th class='outer' $style_th>Possible Teachers</th>
                    </tr>
                    <tr>
                        <td $style_td>";
        $head = ['is_expand','ID','First Name','Last Name','Possible Room(s)'];
        $body = ['id','first_name','last_name','rooms'];
        $html .= standard_widget_table($access_level,self::$widget_id,$teachers['teachers'],__CLASS__,$head,$body,'teachers');
        $html .= "</td></tr></table><br>";

        # Get flat teacher rooms to use in intersection check
        $teacher_rooms_flat = self::get_teacher_rooms($teachers);

        # Get rooms for the course to use in the intersection check
        $rooms = self::get_course_rooms($course_id);
        $rooms_flat = array_map(function($item) { return $item['id']; }, $rooms);

        $style_th = empty($rooms) ? "style='background-color:#FF0000; border:1px #FF0000 solid'" : "";
        $style_td = empty($rooms) ? "style='border:1px #FF0000 solid'" : "";

        $html .= "<table class='outer'>
                    <tr class='outer'>
                        <th class='outer' $style_th>Possible Rooms</th>
                    </tr>
                    <tr>
                        <td $style_td>";
        $head = ['ID','Room','Capacity'];
        $body = ['id','num','capacity'];
        $html .= standard_widget_table($access_level,self::$widget_id,$rooms,__CLASS__,$head,$body,'rooms');
        $html .= "</td></tr></table><br>";

        # Get the intersection of rooms for course and teachers
        $int = array_intersect($rooms_flat,$teacher_rooms_flat);
        $sql = "SELECT r.id, r.num, r.max as capacity FROM rooms r WHERE id IN (".implode(',',$int).")";
        $room_intersection = query($sql,__LINE__);

        $style_th = empty($room_intersection) ? "style='background-color:#FF0000; border:1px #FF0000 solid'" : "";
        $style_td = empty($room_intersection) ? "style='border:1px #FF0000 solid'" : "";

        $html .= "<table class='outer'>
                    <tr class='outer'>
                        <th class='outer' $style_th>Room Intersection for Course and Teachers</th>
                    </tr>
                    <tr>
                        <td $style_td>";
        $head = ['ID','Room','Capacity'];
        $body = ['id','num','capacity'];
        $html .= standard_widget_table($access_level,self::$widget_id,$room_intersection,__CLASS__,$head,$body,'room_intersection');
        $html .= "</td></tr></table><br>";

        # Get the possible time slots for the course
        $time_slots = self::get_course_time_slots($course_id, $scheduling_year_id);
        $course_times_flat = array_map(function($item) { return $item['time_slot_id']; }, $time_slots);

        $style_th = empty($time_slots) ? "style='background-color:#FF0000; border:1px #FF0000 solid'" : "";
        $style_td = empty($time_slots) ? "style='border:1px #FF0000 solid'" : "";

        $html .= "<table class='outer'>
                    <tr class='outer'>
                        <th class='outer' $style_th>Possible Time Slots</th>
                    </tr>
                    <tr>
                        <td $style_td>";
        $head = ['Term','Day','Period'];
        $body = ['term_abbr','day_name','period_abbr'];
        $html .= standard_widget_table($access_level,self::$widget_id,$time_slots,__CLASS__,$head,$body,'time_slots');
        $html .= "</td></tr></table><br>";

        # Get the intersection of time slots for course and teachers
        $time_slot_intersection = self::get_course_teacher_time_slot_intersection($course_times_flat, $all_teacher_times, $scheduling_year_id);

        $style_th = empty($time_slot_intersection) ? "style='background-color:#FF0000; border:1px #FF0000 solid'" : "";
        $style_td = empty($time_slot_intersection) ? "style='border:1px #FF0000 solid'" : "";

        $html .= "<table class='outer'>
                    <tr class='outer'>
                        <th class='outer' $style_th>Time Slot Intersection for Course and Teachers</th>
                    </tr>
                    <tr>
                        <td $style_td>";
        $head = ['Term','Day','Period'];
        $body = ['term_name','day_name','period_name'];
        $html .= standard_widget_table($access_level,self::$widget_id,$time_slot_intersection,__CLASS__,$head,$body,'time_slot_intersection');
        $html .= "</td></tr></table><br>";

        # Get requests
        $sql = "SELECT
                    cr.id
                    ,u.id as user_id
                    ,u.first_name
                    ,u.last_name
                    ,gl.name as grade_level
                    ,concat(ut.name,' ',counselors.last_name) as counselor
                    ,CASE WHEN g.name IS NULL THEN '--' ELSE g.name END as house
                FROM courses_requests cr
                JOIN users u ON u.id = cr.user_id
                JOIN users_school us ON us.district_id = $School->district_id AND us.status_id = 9 AND us.user_id = u.id
                JOIN grade_levels gl ON gl.id = us.grade_id
                LEFT JOIN assigned_to at ON at.user_id = u.id AND at.school_id = us.school_id
                LEFT JOIN users counselors ON counselors.id = at.counselor_id
                LEFT JOIN users_titles ut ON ut.id = counselors.title_id
                LEFT JOIN group_users gu ON gu.user_id = u.id AND gu.year_id = $scheduling_year_id
                LEFT JOIN groups g ON g.id = gu.group_id AND g.is_house = 1
                WHERE cr.course_id = $course_id
                ORDER BY u.last_name, u.first_name";
        $requests = query($sql,__LINE__);
        $num_requests = count($requests);

        $html .= "<table class='outer'>
                    <tr class='outer'>
                        <th class='outer'>Requests ($num_requests)</th>
                    </tr>
                    <tr>
                        <td>";
        $head = ['ID','First Name','Last Name','Grade','House','Counselor','Actions'];
        $body = ['user_id','first_name','last_name','grade_level','house','counselor','actions'=>['delete']];
        $html .= standard_widget_table($access_level,self::$widget_id,$requests,__CLASS__,$head,$body,'requests');
        $html .= "</td></tr></table><br>";

        $html .= <<<HTML
                <br><br>
                <a class='btn' onclick='parent.closeIFrame();'>Close</a>
                <br><br>
HTML;

        # Return
        return parent::header(__CLASS__, $title, $classes) . $html . parent::footer();
    }

    public static function save_section($params, $edit_mode=false) {
        global $User, $School;
        $params = escape_all($params);

        if($edit_mode) {
            query("UPDATE sections_scheduling_terms SET term_id = {$params['term_id_select']} WHERE sec_sched_id = {$params['id']}",__LINE__);

            # If this is a multi-part course, then teacher changes need to carry across all parts
            $sql = "SELECT id
                    FROM sections_scheduling
                    WHERE course_id IN (SELECT id FROM courses_split_by_term
                    WHERE course_id IN (SELECT course_id FROM courses_split_by_term WHERE id IN
                                       (SELECT course_id FROM sections_scheduling WHERE id = {$params['id']})))
                    AND section_num IN (SELECT section_num FROM sections_scheduling WHERE id = {$params['id']})";
            $ids = query($sql,__LINE__);

            if( !empty($ids) ) {
                foreach($ids as $id) {
                    query("DELETE FROM sections_scheduling_teachers WHERE sec_sched_id = {$id['id']}",__LINE__);

                    if( !empty($params['teacher_ids']) ) {
                        foreach($params['teacher_ids'] as $tid) {
                            query("INSERT INTO sections_scheduling_teachers (sec_sched_id, teacher_id, school_id, year_id)
                           VALUES ({$id['id']},{$tid},{$School->id},{$params['year_id']})",__LINE__);
                        }
                    }
                }
            }
        }
        else {
            # Find out if this is a split course
            $sql = "SELECT * FROM courses_split_by_term WHERE course_id = {$params['parent_id']}";
            $split_courses = query($sql,__LINE__);

            if( !empty($split_courses) ) {
                foreach($split_courses as $course_id => $course) {

                    # Get the max section number
                    $sql = "SELECT MAX(section_num) as section_num FROM sections_scheduling WHERE course_id = {$course['id']}";
                    $res = query($sql,__LINE__);
                    $row = array_pop($res);
                    $section_num = empty($row['section_num']) ? 1 : $row['section_num'] + 1;

                    $sql = "INSERT INTO sections_scheduling (course_id, school_id, district_id, year_id, section_num, created_by)
                            VALUES ({$course['id']}, {$School->id},{$School->district_id},{$params['year_id']},{$section_num},{$User->id})";
                    $id = save($sql,__LINE__);

                    if( !empty($params['term_id_select']) ) {
                        $sql = "INSERT INTO sections_scheduling_terms (sec_sched_id, term_id, school_id, year_id)
                               VALUES ({$id}, {$params['term_id_select']},{$School->id},{$params['year_id']})";
                        query($sql,__LINE__);
                    }
                    else {
                        # Find out what term number this course is and grab that term based on the sort order
                        $term_no = $course['term_no'];
                        $sql = "SELECT dct.id
                                FROM school_cal_terms sct
                                JOIN district_cal_terms dct ON dct.id = sct.district_cal_term_id
                                WHERE sct.school_id = $School->id AND dct.year_id = {$params['year_id']}
                                AND dct.sort_order = $term_no";
                        $res = query($sql,__LINE__);
                        $row = array_pop($res);

                        $sql = "INSERT INTO sections_scheduling_terms (sec_sched_id, term_id, school_id, year_id)
                               VALUES ({$id}, {$row['id']},{$School->id},{$params['year_id']})";
                        query($sql,__LINE__);
                    }

                    if( !empty($params['teacher_ids']) ) {
                        foreach( $params['teacher_ids'] as $teacher_id ) {
                            query("INSERT INTO sections_scheduling_teachers (sec_sched_id, teacher_id, school_id, year_id)
                                   VALUES ({$id}, {$teacher_id},{$School->id},{$params['year_id']})",__LINE__);
                        }
                    }
                }
            }
        }

        return TRUE;
    }

    public static function add_section($params){
        return self::add_edit_section($params, false);
    }

    public static function edit_courses_inner($params){
        return self::add_edit_section($params, true);
    }

    public static function add_edit_section($params, $edit_mode=false) {
        global $School;

        # Set up
        $classes = array('standard_widget', 'widget_student_list');
        $html = '';

        if(!empty($_POST['modal_save'])){
            self::save_section($_POST, $edit_mode);
            return widget::$save_success_close;
        }

        if($edit_mode) {
            $title = "Edit Section";

            # Buttons
            $buttons = "<button class='btn btn-success'>
                            Update &nbsp;<i class='fa fa-arrow-circle-right'></i>
                        </button>&nbsp;
                        <input type='hidden' name='id' value='{$params['id']}' />";

            # Get section info
            $res = query("SELECT ss.* FROM sections_scheduling ss WHERE ss.id = '{$params['id']}'",__LINE__);
            $section = array_pop($res);
            $year_id = $section['year_id'];
            $csbt_id = $section['course_id'];

            $sql = "SELECT course_id, term_no FROM courses_split_by_term WHERE id = $csbt_id";
            $res = query($sql,__LINE__);
            $row = array_pop($res);
            $csbt_course_id = $row['course_id'];
            $term_limiting_sql = " AND csbt.term_no = {$row['term_no']} ";
            $is_split_course = 0;
            

            $selected_terms = query("SELECT term_id as id FROM sections_scheduling_terms WHERE sec_sched_id = '{$params['id']}'",__LINE__);
            $selected_terms = @array_keys($selected_terms);

            $selected_teachers = query("SELECT teacher_id as id FROM sections_scheduling_teachers WHERE sec_sched_id = '{$params['id']}'",__LINE__);
            $selected_teachers = @array_keys($selected_teachers);

            //$selected_houses = query("SELECT house_id as id FROM courses_possible_houses WHERE course_id = '{$params['id']}'",__LINE__);
            //$selected_houses = @array_keys($selected_houses);
        }
        else {
            $title = "Add Section";

            $csbt_course_id = $params['parent_id'];
            $term_limiting_sql = "";
            $selected_terms = array();
            $selected_teachers = array();

            # Buttons
            $buttons = "<button class='btn btn-success'>
                            Add &nbsp;<i class='fa fa-arrow-circle-right'></i>
                        </button>&nbsp;
                        <input type='hidden' name='parent_id' value='{$params['parent_id']}' />";

            # Get course info
            $res = query("SELECT c.* FROM courses c WHERE c.id = '{$params['parent_id']}'",__LINE__);
            $course = array_pop($res);
            $year_id = $course['dis_cal_yr_id'];

            # Find out if this is a split course
            $sql = "SELECT * FROM courses_split_by_term WHERE course_id = {$params['parent_id']} AND no_terms > 1";
            $res = query($sql,__LINE__);
            $is_split_course = empty($res) ? 0 : 1;
        }

        # Terms
        $terms = self::get_terms($year_id);
        $no_terms_in_schedule = count($terms);
        $sql = "SELECT
                    dct.id
                    ,dct.term_name as name
                FROM courses_possible_timeslots cpt
                JOIN courses c ON c.id = cpt.course_id
                JOIN courses_split_by_term csbt ON csbt.course_id = c.id
                JOIN district_cal_terms dct ON dct.id = cpt.term_id
                    AND dct.sort_order >= csbt.term_no
                    AND (csbt.no_terms - csbt.term_no) <= ({$no_terms_in_schedule} - dct.sort_order)
                WHERE csbt.course_id = {$csbt_course_id}
                $term_limiting_sql
                GROUP BY id
                ORDER BY dct.sort_order";
        $term_options = query($sql,__LINE__);
        $terms_select = get_multiselect('term_id',$term_options,' title =\'Term:\'', 'span4', @$selected_terms, ['rule' => 'required,Term is required']);

        # Teachers
        $sql = "SELECT u.id, concat(u.first_name,' ',u.last_name) as name
                FROM users_school us
                JOIN users u on u.id = us.user_id
                JOIN courses_possible_teachers cpt ON cpt.course_id = $csbt_course_id AND cpt.teacher_id = u.id
                WHERE us.school_id = $School->id
                AND us.role_id = 2
                ORDER BY u.last_name";
        $teacher_options = query($sql,__LINE__);
        $teachers_select = get_multiselect('teacher_ids[]', $teacher_options, 'multiple title =\'Teachers:\'', 'span4', @$selected_teachers, [
            'rule' => 'required,At least one teacher is required']);

        # Possible Houses
        /*$house_options = query("SELECT g.id, concat(gl.description,': ',g.name) as name
                                FROM groups g
                                JOIN grade_levels gl ON gl.id = g.grade_level_id
                                WHERE g.school_id = $School->id
                                AND g.is_house = 1
                                ORDER BY gl.sort_order, g.name",__LINE__);
        $houses_select = get_multiselect('house_ids[]',$house_options,' multiple title =\'Houses:\'', 'span4', @$selected_houses, '');*/


        $html .= <<<HTML
            <form id='form' name='this_widget' method='post'>
            <table class='table-form'>
HTML;
            if( $is_split_course == 0 ) {
        $html .= <<<HTML
                <tr>
                    <th valign="top">Term</th>
                    <td valign="top">$terms_select</td>
                </tr>
HTML;
            }
        $html .= <<<HTML
                <tr id="teacher_ids">
                    <th valign="top">Teacher(s)</th>
                    <td valign="top">$teachers_select</td>
                </tr>
                <!--
                <tr>
                    <th valign="top">House</th>
                    <td valign="top"></td>
                </tr>
                -->
            </table>
            <br><br>
            $buttons
            &nbsp;
            <a class='btn' onclick='parent.closeIFrame();'>Cancel</a>
            <input type='hidden' name='modal_save' value='1' />
            <input type='hidden' name='year_id' value={$year_id} />

            <script type="text/javascript">
                $('#form').validationEngine({
                        prettySelect:true,
                        useSuffix: '_chosen'
                });
            </script>
            </form>
HTML;
        return parent::header(__CLASS__, $title, $classes) . $html . parent::footer();
    }

    # View
    public function view() {
        global $User, $School;

        # Setup
        $title = "Schedule Course Sections";
        $classes = array('standard_widget', 'widget_student_list');
        $html = '';
        $total_violations = 0;

        # Check View Access
        if( parent::view_access_denied($User->id, self::$widget_id, $School->id) ) {
            return widget::no_view_access_template(__CLASS__, $title, $classes);
        }

        # Get user's access level to pass to the standard_widget_table and add_button
        $access_level = check_widget_permissions($User->id, self::$widget_id, $School->id);

        # Context help
        $context_html = "These are the courses for which students have made requests equal to at least the section minimum.
                        Courses for which there are not enough requests will not be scheduled.  Based on the
                        number of requests and section minimum and maximum sizes, we have suggested the number of
                        sections to create.";
        $html .= context_help_widget($context_html, 2091) . "<br>";

        # See if there's a scheduling_year_id in the URL
        if( empty ($_GET['year_id']) ) {

            $current_year_id = get_current_year($School->id);

            # Get the next year
            $sql = "SELECT dcy.id
                    FROM school_cal_years scy
                    JOIN district_cal_years dcy ON scy.district_cal_year_id = dcy.id
                    WHERE scy.school_id = $School->id
                    AND scy.start_date > (SELECT end_date FROM district_cal_years WHERE id = $current_year_id)
                    ORDER BY scy.start_date ASC
                    LIMIT 1";
            $res = query($sql,__LINE__);
            if( ! empty($res) ) {
                $row = array_pop($res);
                $scheduling_year_id = $row['id'];
            }
            else {
                $scheduling_year_id = $current_year_id;
            }
        }
        else {
            $scheduling_year_id = $_GET['year_id'];
        }

        $years = query("SELECT dcy.id, dcy.year_name as name
                        FROM school_cal_years scy
                        LEFT JOIN district_cal_years dcy ON dcy.id = scy.district_cal_year_id
                        WHERE scy.school_id = $School->id
                        ORDER BY scy.start_date DESC",__LINE__);
        $year_options = get_multiselect('year',$years,' title =\'Year:\'', 'span3', @$scheduling_year_id, []);

        $html .= <<<HTML
           <div class='alert alert-info'>
           <form id='form' name='this_widget' method='post' action='' class='filter-year-requests'>
                <table>
                    <tr>
                        <td width='150'>Scheduling Year:</td>
                        <td width='350'>$year_options</td>
                        <td width='350'><input type='submit' name='filter' value='Filter'/></td>
                    </tr>
                </table>
           </form>
           </div>
           <script>
                $('form.filter-year-requests').on('submit', function() {
                    var year_id = $(this).find('select[name="year_select"]').val();
                    window.location.href = window.location.origin + window.location.pathname
                                            + '?year_id=' + year_id
                                            + window.location.hash;
                    return false;
                });
           </script>
HTML;


        # Fill the COURSES_SPLIT_BY_TERM table if empty (or check and re-run to make sure any changes incorporated)
        # Before you do, make sure no_terms is set correctly

        # Figure out the relationship between term_len and no_terms
        $terms = self::get_terms($scheduling_year_id);
        $num_terms_in_schedule = count($terms);

        if($num_terms_in_schedule == 3) {
            query("UPDATE courses SET no_terms = 3 WHERE term_len = 1 AND dis_cal_yr_id = $scheduling_year_id");
            query("UPDATE courses SET no_terms = 2 WHERE term_len = 2 AND dis_cal_yr_id = $scheduling_year_id");
            query("UPDATE courses SET no_terms = 1 WHERE term_len = 3 AND dis_cal_yr_id = $scheduling_year_id");
        }

        $sql = "SELECT c.id, c.term_len, c.no_terms, c.meet_pattern_id, c.num_periods
                FROM courses_requests cr
                JOIN courses c ON cr.course_id = c.id
                WHERE c.school_id = {$School->id}
                AND c.dis_cal_yr_id = {$scheduling_year_id}
                GROUP BY c.id";
        $courses = query($sql,__LINE__);

        # Get the already split courses so that we do not duplicate any
        $sql = "SELECT course_id
                FROM courses_split_by_term csbt
                JOIN courses c ON c.id = csbt.course_id
                WHERE c.school_id = {$School->id}
                AND c.dis_cal_yr_id = {$scheduling_year_id}";
        $res = query($sql,__LINE__);
        $courses_already_split = array_map(function($item) { return $item['course_id']; }, $res);

        if( !empty($courses) ) {
            foreach($courses as $key => $value) {

                if( !in_array($value['id'],$courses_already_split) ) {
                    for($i = 1; $i <= $value['no_terms']; $i++) {
                        $sql = "INSERT INTO courses_split_by_term (course_id, term_no, no_terms, meet_pattern_id, num_periods)
                                VALUES ({$value['id']},{$i},{$value['no_terms']},{$value['meet_pattern_id']},{$value['num_periods']})";
                        query($sql,__LINE__);
                    }
                }
            }
        }


        $sql = "SELECT
                    c.id
                    ,c.course_no
                    ,c.name
                    ,COUNT(cr.id) as no_requests
                    ,c.section_min
                    ,c.max_enrollment
                    ,c.no_terms
                    ,CEIL(COUNT(cr.id) / c.max_enrollment) as no_sections_suggested
                FROM courses c
                JOIN courses_requests cr ON cr.course_id = c.id
                JOIN users u ON u.id = cr.user_id
                JOIN users_school us ON us.district_id = $School->district_id AND us.status_id = 9 AND us.user_id = u.id
                WHERE c.dis_cal_yr_id = $scheduling_year_id
                AND cr.school_id = $School->id
                AND c.school_id = $School->id
                GROUP BY c.id
                HAVING no_requests >= c.section_min
                ORDER BY c.name";
        $courses = query($sql,__LINE__);
        $no_courses = count($courses);

        if( !empty($courses) ) {
            foreach($courses as $course_id => $course) {

                $sql = "SELECT
                            sec_sched.id
                            ,sec_sched.section_num
                            ,csbt.term_no
                            ,GROUP_CONCAT(DISTINCT dct.abbr ORDER BY dct.abbr SEPARATOR '<br>') as terms
                            ,GROUP_CONCAT(DISTINCT CONCAT(u.first_name,' ',u.last_name) ORDER BY u.last_name SEPARATOR '<br>') as teachers
                        FROM sections_scheduling sec_sched
                        JOIN courses_split_by_term csbt ON csbt.id = sec_sched.course_id
                        LEFT JOIN sections_scheduling_terms sec_sched_terms ON sec_sched_terms.sec_sched_id = sec_sched.id
                        LEFT JOIN district_cal_terms dct ON dct.id = sec_sched_terms.term_id
                        LEFT JOIN sections_scheduling_teachers sec_sched_teach ON sec_sched_teach.sec_sched_id = sec_sched.id
                        LEFT JOIN users u ON u.id = sec_sched_teach.teacher_id
                        WHERE csbt.course_id = $course_id
                        GROUP BY sec_sched.id
                        ORDER BY sec_sched.section_num, csbt.term_no";
                $sections = query($sql,__LINE__);

                if( !empty($sections) ) {
                    foreach($sections as $section_id => $section) {
                        $sections[$section_id]['terms'] = empty($sections[$section_id]['terms']) ? "--" : $sections[$section_id]['terms'];
                        $sections[$section_id]['teachers'] = empty($sections[$section_id]['teachers']) ? "--" : $sections[$section_id]['teachers'];
                    }
                }

                $courses[$course_id]['no_sections_created'] = count($sections) / $course['no_terms'];
                $violations = self::check_violations($course_id, $scheduling_year_id);
                $courses[$course_id]['violations'] = $violations;

                $total_violations += $violations;

                $courses[$course_id]['inner_data'] = $sections;

                $courses[$course_id]['inner_head'] = ['ID','Section No.','Part No.','Term','Teacher(s)','Actions'];
                $courses[$course_id]['inner_body'] = ['id','section_num','term_no','terms','teachers','actions'=>["edit_courses_inner","delete_courses_inner"],'after_table'=>['add']];
                $courses[$course_id]['inner_meta'] = ['add_button_name' => 'add_section'];
            }
        }

        $html .= "<div class='alert alert-success'>$no_courses courses found.";
        $courses_below_minimum = self::get_courses_below_minimum($scheduling_year_id);
        $num_courses_below_minimum = count($courses_below_minimum);

        if($num_courses_below_minimum > 0) {
            $html .= "&nbsp;&nbsp;$num_courses_below_minimum courses had too few requests.  Fix these to run your schedule.
                      <a href=# style='font-weight: lighter;' onclick='get_modal(\"".__CLASS__."\",\"courses_below_minimum\",\"".$scheduling_year_id."\");'>
                         Which courses?
                      </a></div>";
        }
        else {
            $html .= "</div>";
        }

        $params = ['scheduling_year_id'=>$scheduling_year_id];
        $params = permissions::prep_params($params);
        $html .= "<div class='alert alert-danger'>
                        <a class='btn' onclick='get_modal(\"".__CLASS__."\",\"create_sections\",\"".$scheduling_year_id."\");'>Create Sections</a>&nbsp;&nbsp;
                        <a class='btn' onclick='get_modal(\"".__CLASS__."\",\"delete_sections\",\"".$params."\");'>Delete Sections</a>&nbsp;&nbsp;
                        <a class='btn' onclick='get_modal(\"".__CLASS__."\",\"view_assigned_fte\",\"".$scheduling_year_id."\");'>View Assigned FTE</a>&nbsp;&nbsp;
                        <a class='btn' onclick='get_modal(\"".__CLASS__."\",\"check_fix_fte\",\"".$scheduling_year_id."\");'>Check and Fix FTE</a>
                  </div>";

        # Dump the data
        $html .= "<div class='alert alert-warning'>";
        $html .= "<h2>Dump Data for Scheduling</h2>";
        if($total_violations > 0) {
            $html .= "You have violations to resolve in individual courses.  You may not dump data until you have fixed all errors.";
        }
        elseif($num_courses_below_minimum > 0) {
            $html .= "You have some courses with too few requests.  You may not dump data until all courses have enough requests to be offered.";
        }
        else {
            /*$html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=terms'>Terms</a><br>";
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=days'>Days</a><br>";
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=periods'>Periods</a><br><br>";

            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=students'>Students</a><br>";
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=teachers'>Teachers</a><br>";
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=teachers_fte'>Teachers FTE</a><br><br>";

            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=courses'>Courses</a><br>";
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=requests'>Requests</a><br>";
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=courses_teachers'>Course Teachers (Necessary?)</a><br>";
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=rooms'>Rooms</a><br>";
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=teachers_rooms'>Teacher Rooms</a><br>";
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=courses_rooms'>Course Rooms</a><br><br>";

            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=sections_teachers_terms'>Sections with Teacher and Term</a><br>";
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=courses_sequence'>Course Sequences</a><br>";
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=courses_time_slots'>Course Time Slots</a><br>";
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}&data_type=courses_sizes'>Course Sizes</a><br>";*/
            $html .= "<a target='_blank' href='/schedule_data.php?school_id={$School->id}&year_id={$scheduling_year_id}'>Generate Dump File</a><br>";
        }
        $html .= "</div>";


        # Table HTML
        $head = ['is_expand','ID','No.','Course','No. Requests','Min.','Max.','Sugg. No. Sections','No. Sections Created','Violations','Actions'];
        $body = ['id','course_no','name','no_requests','section_min','max_enrollment','no_sections_suggested','no_sections_created','violations','actions'=>['details','edit','delete']];
        $html .= standard_widget_table($access_level,self::$widget_id,$courses,__CLASS__,$head,$body,'courses');

        # Return
        return parent::header(__CLASS__, $title, $classes) . $html . parent::footer();
    }
}