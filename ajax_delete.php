<?php
require 'boot.php';

$class = $_GET['class'];
$category = $_GET['category'];
$id = $_GET['id'];
$object = $class."_".$category;

global $School;

error_log("Deleting $id from $object");

/*
 *  Sections widget
 *
 *  Delete Meeting Time row
 */
if($object == 'sections_meeting_time') {
    $sql = "delete from section_meeting_times where id = $id limit 1";
    query($sql, __LINE__);
    echo true;
}
/*
 * Departments Widget
 *
 * This deletes the department and ALL of the group members.
 *
 */
if($object == 'departments_departments'){
    query("delete from group_users where group_id = $id",__LINE__);
    query("delete from groups where id = $id LIMIT 1",__LINE__);
    echo '1';
}
if($object == 'departments_departments_inner'){
    query("delete from group_users where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Departments Members Widget (on department page)
 *
 */
if($object == 'department_members_members'){
    query("delete from group_users where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Positions
 */
if($object == 'positions_positions'){
    if($School->is_district == 1){
        $sql = "select * from users_school where position_id = $id and district_id = $School->district_id";
        $result = query($sql,__LINE__);
        if($result){
            echo '0';
        }
        else{
            query("delete from staff_category_school where staff_category_id = $id LIMIT 1",__LINE__);
            query("delete from staff_category where id = $id LIMIT 1",__LINE__);
            echo '1';
        }
    }
    else{
        $sql = "select * from users_school where position_id = $id and school_id = $School->id";
        $result = query($sql,__LINE__);
        if($result){
            echo '0';
        }
        else{
            query("delete from staff_category_school where staff_category_id = $id LIMIT 1",__LINE__);
            echo '1';
        }
    }

}

/*
 * Calendar Years
 */
// District Years
if($object == 'cal_years_cal_years'){

    $sql = "select id from district_cal_terms where year_id = $id";
    $result_terms = query($sql,__LINE__);

    $sql = "select id from courses where dis_cal_yr_id = $id";
    $result_courses = query($sql,__LINE__);

    $sql = "select id from grade_levels_historical where dis_cal_yr_id = $id";
    $result_historical = query($sql,__LINE__);

    if($result_terms){
        echo '0';
    }
    elseif($result_courses){
        echo '0';
    }
    elseif($result_historical){
        echo '0';
    }
    else{
        query("delete from school_cal_years where district_cal_year_id = $id",__LINE__);
        query("delete from district_cal_years where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

// School Years
if($object == 'cal_years_cal_years_school'){

    # Get the district year id
    $sql = "select district_cal_year_id from school_cal_years where id = $id";
    $res = query($sql,__LINE__);
    $row = array_pop($res);
    $district_cal_year_id = $row['district_cal_year_id'];

    $sql = "select sct.id
            from school_cal_terms sct
            left join district_cal_terms dct on dct.id = sct.district_cal_term_id
            where dct.year_id = $district_cal_year_id
            and school_id = $School->id";
    $result_terms = query($sql,__LINE__);
    $sql = "select id from courses where dis_cal_yr_id = $district_cal_year_id and school_id = $School->id";
    $result_courses = query($sql,__LINE__);
    if($result_terms){
        echo '0';
    }
    elseif($result_courses){
        echo '0';
    }
    else{
        query("delete from school_cal_years where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

/*
 * Calendar Terms
 */
// District Terms
if($object == 'cal_terms_cal_terms'){
    $sql = "select id from school_cal_terms where dis_cal_term_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else{
        query("delete from school_cal_terms where district_cal_term_id = $id",__LINE__);
        query("delete from district_cal_terms where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

// School Terms
if($object == 'cal_terms_cal_terms_school'){

    # Get the district term id
    $sql = "select district_cal_term_id from school_cal_terms where id = $id";
    $res = query($sql,__LINE__);
    $row = array_pop($res);
    $district_cal_term_id = $row['district_cal_term_id'];

    $sql = "select id from section_terms where term_id = $district_cal_term_id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else{
        query("delete from school_cal_terms where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

// School Terms to Grading Periods
if($object == 'cal_terms_cal_terms_school_inner') {

    // It is not necessary to check anything because this is just an association; if set the scheduler will use it, and if not, it won't
    query("delete from terms_grading_periods where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Calendar Days
 */

if($object == 'cal_days_cal_days'){
    query("delete from days where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Bell Schedule
 */

if($object == 'cal_bell_schedule_cal_bell_schedule'){
    $sql = "select id from school_schedule_detail where bell_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else{
        query("delete from bell_schedules where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

/*
 * Periods Bells
 */

if($object == 'cal_bell_schedule_cal_bell_schedule_inner'){
    $sql = "select period_id from periods_bells where id = $id";
    $res = query($sql,__LINE__);
    $row = array_pop($res);
    $period_id = $row['period_id'];

    $sql = "select id from section_periods where period_id = $period_id";
    $result = query($sql,__LINE__);

    if($result){
        echo '0';
    }
    else{
        query("delete from periods_bells where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

/*
 * Periods
 */
if($object == 'periods_periods'){
    $sql = "select * from section_periods where period_id = $id";
    $section_result = query($sql,__LINE__);
    $sql = "select * from periods_bells where period_id = $id";
    $bell_result = query($sql,__LINE__);
    if($section_result OR $bell_result){
        echo '0';
    }
    else{
        query("delete from periods where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

/*
 * Holidays
 */
if($object == 'holidays_holidays'){
    if($School->is_district == 1){
        query("delete from holidays where id = $id LIMIT 1",__LINE__);
        query("delete from holiday_schools where holiday_id = $id LIMIT 1",__LINE__);
        echo '1';
    }
    else{
        $res = query("select created_by from holidays where id = $id",__LINE__);
        $row = array_pop($res);
        $created_by = $row['created_by'];
        if($created_by == $School->id){
            query("delete from holidays where id = $id LIMIT 1",__LINE__);
            query("delete from holiday_schools where holiday_id = $id LIMIT 1",__LINE__);
            echo '1';
        }
        else{
            echo '0';
        }
    }
}

/*
 * Attendance Groups
 */
// District Groups
if($object == 'marks_tags_marks_tags'){
    $sql = "select am.* from att_marks am where am.att_group_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else{
        query("delete from att_group where id = $id LIMIT 1",__LINE__);
        query("delete from att_group_school where att_group_id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}
// School Groups
if($object == 'marks_tags_marks_tags_school'){
    $sql = "select ams.* from att_marks_school ams left join att_marks am on am.id = ams.att_mark_id where am.att_group_id = $id and ams.school_id = $School->id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else{
        query("delete from att_group_school where att_group_id = $id and school_id = $School->id LIMIT 1",__LINE__);
        echo '1';
    }
}

/*
 * Marks & Tags 
 */
// District Marks & Tags
if($object == 'marks_tags_marks_tags_inner'){
    $sql = "select att_mark_id from attendance where att_mark_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else{
        query("delete from att_marks where id = $id LIMIT 1",__LINE__);
        query("delete from att_marks_school where att_mark_id = $id",__LINE__);
        echo '1';
    }
}
// School Marks & Tags
if($object == 'marks_tags_marks_tags_school_inner'){
    $sql = "select att_mark_id from attendance where att_mark_id = $id and school_id = $School->id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else{
        query("delete from att_marks_school where att_mark_id = $id and school_id = $School->id LIMIT 1",__LINE__);
        echo '1';
    }
}

/*
 * Grades 
 */
// Delete a grade from the grades widget
if($object == 'grades_grades'){
    $sql = "select id from grades_scales_map where grade_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo 0;
    }
    else{
        query("delete from grades where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

// Delete a grade from a grading scale
if($object == 'grading_scales_grading_scales_inner'){
    query("delete from grades_scales_map where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Grading Scales
 */
if($object == 'grading_scales_grading_scales'){
    $sql = "select id from grading_scales_school where grading_scale_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo 0;
    }
    else {
        query("delete from grading_scales_school where grading_scale_id = $id",__LINE__);
        query("delete from grading_scales where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}
if($object == 'grading_scales_grading_scales_school'){
    $sql = "select id from sections where assignment_based_id = $id or standards_based_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo 0;
    }
    else{
        query("delete from grading_scales_school where grading_scale_id = $id and school_id = $School->id LIMIT 1",__LINE__);
        echo '1';
    }
}

/*
 * Grading Periods
 */
if($object == 'grading_periods_grading_periods'){
    $sql = "select id from grading_periods_school where grading_period_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo 0;
    }
    else{
        query("delete from grading_periods where id = $id LIMIT 1",__LINE__);
        query("delete from grading_periods_school where grading_period_id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}
if($object == 'grading_periods_grading_periods_school'){
    $res = query("select gps.grading_period_id from grading_periods_school gps where id = $id",__LINE__);
    $row = array_pop($res);
    $grading_period_id = $row['grading_period_id'];
    $sql = "select sgp.id from section_grading_periods sgp left join sections s on s.id = sgp.section_id where sgp.grading_period_id = $grading_period_id and s.school_id = $School->id";
    $result = query($sql,__LINE__);
    if($result){
        echo 0;
    }
    else{
        query("delete from grading_periods_school where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

/*
 * Grading Comments
 */
if($object == 'grading_comments_grading_comments'){

    query("delete from grading_comments where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * GPA Calculation
 */
if($object == 'gpa_calculation_gpa_calculation'){

    query("delete from gpa_calculation where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Honor Roll 
 */

if($object == 'honor_roll_honor_rolls'){
    query("delete from honor_roll where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Maintenance Queue
 */

if($object == 'maintenance_queue_maintenance_queue'){
    query("delete from maintenance_q where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Attendance Triggers
 */

if($object == 'attendance_triggers_attendance_trigger'){
    query("delete from att_triggers where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Attendance Triggers
 */

if($object == 'context_help_context_help'){
    query("delete from context_help where id = $id LIMIT 1",__LINE__);
    query("delete from context_help_widgets where context_help_id = $id 
        LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Course Catalog
 */

# Catalogs
if($object == 'catalogs_catalogs'){
    $sql = "SELECT id FROM courses WHERE catalog_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else {
        query("delete from course_catalog where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

if($object == 'course_catalog_course_catalog'){
    query("delete from courses where id = $id LIMIT 1",__LINE__);
    echo '1';
}

# Course catalog - school level
if($object == 'course_catalog_courses'){
    $sql = "SELECT id FROM sections WHERE course_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else {
        query("delete from courses_requests where course_id = $id LIMIT 1",__LINE__);
        query("delete from courses where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

# Course catalog - professional development
if($object == 'course_catalog_prof_dev_courses'){
    $sql = "SELECT id FROM sections WHERE course_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else {
        query("delete from courses where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

/*
 * Sections
 */

if($object == 'sections_sections'){
    $result = query("select * from schedule where section_id = $id and is_teacher = 0", __LINE__);
    if($result){
        echo 0;
    }
    else {
        query("delete from schedule where section_id = $id", __LINE__);
        query("delete from grades_final where section_id = $id",__LINE__);
        query("delete from sections where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

/*
 * Section Enrollment
 */

if($object == 'sections_sections_inner'){
    global $_SES;

    # Get the id of the student whom we are deleting
    $res = query("select user_id, section_id from schedule where id = $id LIMIT 1",__LINE__);
    $row = array_pop($res);
    $user_id = $row['user_id'];
    $section_id = $row['section_id'];

    # Get user's name to email teacher
    $res = query("SELECT concat(u.first_name,' ',u.last_name) as name FROM users u WHERE u.id = $user_id",__LINE__);
    $row = array_pop($res);
    $user_name = $row['name'];

    # Get the teacher(s) of this class, email addresses, and class name
    $sql = "SELECT u.id, u.first_name, con.value as email_address, concat(c.short_name,', Sec. ',sec.section_num) as section_name
            FROM schedule sched
            JOIN users u ON u.id = sched.user_id
            JOIN contact con ON con.user_id = sched.user_id AND con.field = 'email' AND con.primary_contact = 1
            JOIN courses c ON c.id = sched.course_id
            JOIN sections sec ON sec.id = sched.section_id
            WHERE sched.is_teacher = 1
            AND sched.section_id = $section_id";
    $teachers = query($sql,__LINE__);

    if( !empty($teachers) ) {
        foreach($teachers as $teacher_id => $teacher) {

            $teacher_name = $teacher['first_name'];
            $email_address = $teacher['email_address'];
            $section_name = $teacher['section_name'];

            # Email Teacher(s)
            $body = "Dear $teacher_name,<br><br>The following students have been dropped from your class: $section_name.<br><br>$user_name<br>MIDAS Education";

            if( ! empty($email_address) ) {
                $m = new SimpleEmailServiceMessage();
                $m->addTo($email_address);
                $m->setFrom('MIDAS Education <no-reply@midaseducation.com>');
                $m->setSubject('Notice of Dropped Enrollment');
                $m->setMessageFromString('', $body);
                $_SES->sendEmail($m);
            }
        }
    }

    query("delete from grades_final where student_id = {$user_id} AND section_id = {$section_id}",__LINE__);
    query("delete from schedule where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Levels
 */
if($object == 'levels_levels'){
    if($School->is_district){
        $res = query("select level_id from school_levels_map where id = $id",__LINE__);
        $row = array_pop($res);
        $level_id = $row['level_id'];
        query("delete from school_levels_map where level_id = $level_id and district_id = $School->district_id",__LINE__);
    }
    else{
        query("delete from school_levels_map where id = $id LIMIT 1",__LINE__);
    }
    echo '1';
}

/*
 * Grade Levels
 */
if($object == 'levels_grade_levels'){
    query("delete from school_grade_levels_map where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * District Buildings
 * NB: deletes all entries linking a building to a school and all floors that belong to that building as well
 */
if($object == 'buildings_building'){
    query("delete from buildings_school where building_id = $id",__LINE__);
    query("delete from floors where building_id = $id",__LINE__);
    query("delete from buildings where id = $id LIMIT 1",__LINE__);
    echo '1';
}
if($object == 'buildings_building_inner'){
    query("delete from floors where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * School Buildings
 */
if($object == 'buildings_buildings_school'){
    query("delete from buildings_school where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Facilities
 */
if($object == 'facilities_facility'){
    query("delete from facilities where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Rooms
 */
if($object == 'rooms_room'){
    query("delete from rooms where id = $id LIMIT 1",__LINE__);
    query("delete from rooms_facilities where room_id = $id",__LINE__);
    query("delete from teacher_rooms where room_id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Teacher Rooms
 */
if($object == 'teacher_rooms_teacher_rooms_inner'){
    query("delete from teacher_rooms where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Equipments
 */
if($object == 'equipment_equipment_type'){
    query("delete from equipment_type where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Textbooks
 */
if($object == 'textbooks_textbook'){
    query("delete from textbooks where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Lockers
 */
if($object == 'lockers_locker_type'){
    $sql = "select * from lockers where type_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo 0;
    }
    else{
        query("delete from locker_types where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}

if($object == 'lockers_locker'){
    query("delete from lockers where id = $id LIMIT 1",__LINE__);
    query("delete from lockers_users where locker_id = $id",__LINE__);
    echo '1';
}

if($object == 'lockers_locker_inner'){
    query("delete from lockers_users where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * Banks
 */
if($object == 'bank_info_bank_info') {
    $res = query("SELECT api.id api_id, api.response_id FROM banks bank
                  LEFT JOIN payment_api_records api on bank.recipient_id = api.id
                  WHERE bank.id = $id
                  LIMIT 1;", __LINE__);
    $recipient_api_key = $res[0]['response_id'];
    $api_id = $res[0]['api_id'];

    try {

        if ( !class_exists('Stripe') ) {
            # Stripe Lib
            require(__DIR__.'/assets/stripe-php/lib/config.php');
            require(__DIR__.'/assets/stripe-php/lib/Stripe.php');
            Stripe::setApiKey(STRIPE_API_KEY);
        }

        $recipient = Stripe_Recipient::retrieve($recipient_api_key);
        $res = $recipient->delete();
        if ( $res->deleted ) {
            query("delete from banks where id = $id LIMIT 1",__LINE__);
            query("delete from payment_api_records where id = $api_id LIMIT 1",__LINE__);
            echo 1;
        }
    } catch (Stripe_Error $e) {
        $err = $e->getJsonBody();
        echo $err['error']['message'];
    }
}

/*
 * Google Calendars
 */
if($object == 'google_calendars_google_calendars'){
    $client = get_calendar_client();
    $cal = new Google_CalendarService($client);

    if(auth_calendar($client)){
        @$cal->calendars->delete($id);
        echo '1';
    }

}

/*
 * Module and Widget Permissions
 */
if($object == 'staff_category_permissions_permissions_modules'){

    # Get module name
    $sql = "select w.id, module from permissions p left join widgets w on w.id = p.widget_id where p.id = $id;";
    $res = query($sql,__LINE__);
    $row = array_pop($res);

    # Get all widget IDs for this module
    $sql = "select id, name from widgets where module = '{$row['module']}';";
    $modules_widgets = query($sql,__LINE__);
    $these_widget_ids = [];
    foreach($modules_widgets as $modules_widget){
        $these_widget_ids[] = $modules_widget['id'];
    }
    $these_widget_ids_str = implode(',',$these_widget_ids);
    error_log(print_r($these_widget_ids,1));

    # Delete all permissions records for this school and this module
    $sql = "delete from permissions where widget_id IN ($these_widget_ids_str) and school_id = $School->id;";
    query($sql,__LINE__);

    echo '1';
}

if($object == 'staff_category_permissions_permissions_modules_inner'){
    # Delete all permissions records for this school and this module
    $sql = "delete from permissions where id = $id;";
    query($sql,__LINE__);
    echo '1';
}

/*
 * Course Groups
 */
if($object == 'course_groups_course_group_inner'){
    query("delete from course_groups_courses where id = $id LIMIT 1",__LINE__);
    echo '1';
}

if($object == 'course_groups_course_group'){
    query("delete from course_groups_courses where course_group_id = $id",__LINE__);
    query("delete from course_groups where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * Multi-Part Courses
 */
if($object == 'courses_multi_part_course_combinations'){
    query("delete from courses_multi_part where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * Graduation Requirements
 */
if($object == 'graduation_requirements_setup_graduation_reqs'){
    query("delete from graduation_reqs_sets where id = $id LIMIT 1",__LINE__);
    echo '1';
}

if($object == 'graduation_requirements_setup_graduation_reqs_inner'){
    query("delete from graduation_reqs where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * Graduation Per Year Requirements
 */
if($object == 'per_year_requirements_graduation_year_reqs'){
    query("delete from graduation_year_reqs_sets where id = $id LIMIT 1",__LINE__);
    echo '1';
}

if($object == 'per_year_requirements_graduation_year_reqs_inner'){
    query("delete from graduation_year_reqs where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * Multi-Year Plans
 */
if($object == 'multi_year_plan_multi_year_plan'){
    query("delete from multi_year_plan where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * Multi-Year Settings
 */
if($object == 'multi_year_settings_multi_year_settings'){
    query("delete from multi_year_settings where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * Prerequisites
 */
if($object == 'prerequisites_prerequisite'){
    query("delete from prerequisites where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * Sequences
 */
if($object == 'sequence_sequences'){
    query("delete from sequences where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * Sequences Courses
 */
if($object == 'sequence_sequences_inner'){
    query("delete from sequences_courses where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * Incidents and Points
 */
if($object == 'behavior_incidents_points_incident_type'){
    $sql = "SELECT id FROM behavior_consequences_guidelines WHERE incident_type_id = $id";
    $result = query($sql,__LINE__);
    if($result)
        echo '0';
    else {
        query("delete from behavior_incident_types_school where incident_type_id = $id",__LINE__);
        query("delete from behavior_incident_types where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}
if($object == 'behavior_incidents_points_incident_type_school'){
    $sql = "SELECT id FROM behavior_consequences_guidelines WHERE incident_type_id = $id";
    $result = query($sql,__LINE__);
    if($result)
        echo '0';
    else {
        query("delete from behavior_incident_types_school where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}
/*
 * Consequences
 */
if($object == 'consequences_consequence'){
    query("delete from behavior_consequences_guidelines where consequence_id = $id",__LINE__);
    query("delete from behavior_consequences where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * Consequences Enacted
 */
if($object == 'my_behavior_consequences'){
    query("delete from behavior_consequences_enacted where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * Guidelines
 */
if($object == 'consequences_consequence_inner'){
    query("delete from behavior_consequences_guidelines where id = $id",__LINE__);
    echo '1';
}

/*
 * Incident Management
 */
if($object == 'incident_management_incident_management'){
    query("delete from behavior_actors where incident_id = $id",__LINE__);
    query("delete from behavior_incidents where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * not_in_session district
 */
if($object == 'not_in_session_not_in_session'){
    query("delete from not_in_session_school where not_in_session_id = $id",__LINE__);
    query("delete from not_in_session where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * not_in_session school
 */
if($object == 'not_in_session_not_in_session_school'){
    query("delete from not_in_session_school where id = $id",__LINE__);
    echo '1';
}
/*
 * requests
 */
if($object == 'requests_requests'){
    query("delete from requests where id = $id LIMIT 1",__LINE__);
    echo '1';
}
/*
 * activity reqs
 */
if($object == 'activities_setup_activity_inner'){
    query("delete from activity_reqs where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * activities
 */
if($object == 'activities_setup_activity'){
    query("delete from activity_reqs where activity_id = $id",__LINE__);
    query("delete from activities where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * timesheet periods
 */
if($object == 'timesheet_periods_timesheet_periods'){
    $sql = "SELECT id FROM timesheets WHERE period_id = $id";
    $result = query($sql,__LINE__);
    if($result)
        echo '0';
    else {
        query("delete from timesheet_periods where id = $id",__LINE__);
        echo '1';
    }
}

/*
 * leave requests
 */
if($object == 'leave_requests_leave_requests'){
    query("delete from leave_requests where id = $id",__LINE__);
    echo '1';
}

/*
 * timesheet entries
 */
if($object == 'timesheets_timesheets'){
    query("delete from timesheets where id = $id",__LINE__);
    echo '1';
}
if($object == 'timesheets_admin_timesheets_admin'){
    query("delete from timesheets where id = $id",__LINE__);
    echo '1';
}
/*
 * assignment
 */
if($object == 'assignment_assignment'){

    // First ID passed is assignment_id; second ID is for section
    $sql = "delete from assignments_sections where assignment_id = $id[0] and section_id = $id[1] limit 1";
    query($sql,__LINE__);
    echo '1';
}

/*
 * assignment categories
 */
if($object == 'gradebook_settings_categories') {

    # Get the parent category_id
    $sql = "select category_id from assignment_categories_gp where id = $id";
    $res = query($sql,__LINE__);
    $row = array_pop($res);
    $category_id = $row['category_id'];

    query("delete from assignment_categories_gp where category_id = $category_id",__LINE__);
    query("delete from assignment_categories where id = $category_id",__LINE__);
    query("delete from assignment_grades where assignment_id in (select id from assignments where assignment_category_id = $category_id)",__LINE__);
    query("delete from assignments where assignment_category_id = $category_id",__LINE__);
    echo '1';
}

if($object == 'gradebook_settings_standards'){
    query("delete from assignment_categories where id = $id",__LINE__);
    echo '1';
}


/*
 * addresses
 */
if($object == 'contact_addresses'){
    $res = query("select value from contact where id = $id",__LINE__);
    $row = array_pop($res);
    $address_id = $row['value'];
    query("delete from contact_address where id = $address_id",__LINE__);
    query("delete from contact where id = $id",__LINE__);
    echo '1';
}

/*
 * phones
 */
if($object == 'contact_phones'){
    $res = query("select value from contact where id = $id",__LINE__);
    $row = array_pop($res);
    $phone_id = $row['value'];
    query("delete from contact_phone where id = $phone_id",__LINE__);
    query("delete from contact where id = $id",__LINE__);
    echo '1';
}

/*
 * emails
 */
if($object == 'contact_emails'){
    query("delete from contact where id = $id",__LINE__);
    echo '1';
}

/*
 * student-locker pairings
 */
if($object == 'assigned_to_lockers'){
    query("delete from lockers_users where id = $id",__LINE__);
    echo '1';
}

/*
 * student-parent pairings
 */
if($object == 'parent_guardian_parents'){

    # Update users_school rows linking this parent and student to status of former and remove student reference
    # This is so that the parent stays in the parent directory in a passive way

    # First get the user_id of the parent we are unlinking
    $sql = "SELECT parent_id, student_id FROM parent_student WHERE id = $id";
    $res = query($sql,__LINE__);
    $row = array_pop($res);
    $parent_id = $row['parent_id'];
    $student_id = $row['student_id'];

    # Now get users_school rows linking this parent and student
    $sql = "SELECT us.*
            FROM users_school us
            LEFT JOIN users_school us1 ON us1.id = us.student_users_school_id
            WHERE us1.user_id = $student_id";
    $res = query($sql,__LINE__);

    # If the status of any of these rows is still "Current," update the status to former and add an exit date
    if( ! empty($res) ){
        foreach($res as $value){
            if( $value['status_id'] != 10 ){
                if($value['entry_date'] > date("Y-m-d") ){
                    $sql = "UPDATE users_school SET status_id = 10, entry_date = CURDATE(), exit_date = CURDATE() WHERE id = '{$value['id']}' ";
                }
                else{
                    $sql = "UPDATE users_school SET status_id = 10, exit_date = CURDATE() WHERE id = '{$value['id']}' ";
                }
                query($sql,__LINE__);
            }
        }
    }

    # Delete from parent_student
    query("delete from parent_student where id = $id",__LINE__);
    echo '1';
}

/*
 * drop student from a section
 */
if($object == 'enrollment_enrolled_students'){
    query("delete from schedule where id = $id",__LINE__);
    echo '1';
}

/*
 * concern types
 */
if($object == 'concerns_types_concerns'){
    $sql = "SELECT id FROM concerns WHERE concern_type_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else {
        query("delete from concerns_types where id = $id",__LINE__);
        echo '1';
    }
}

/*
 * concerns
 */
if($object == 'concerns_submit_concerns'){
    query("delete from concerns where id = $id",__LINE__);
    echo '1';
}

/*
 * unlink parent and student (from parent directory)
 */
if($object == 'staff_directory_parents_directory_inner'){

    # Update users_school rows linking this parent and student to status of former
    # This is so that the parent stays in the parent directory in a passive way

    # First get the user_id of the parent we are unlinking
    $sql = "SELECT parent_id, student_id FROM parent_student WHERE id = $id";
    $res = query($sql,__LINE__);
    $row = array_pop($res);
    $parent_id = $row['parent_id'];
    $student_id = $row['student_id'];

    # Now get users_school rows linking this parent and student
    $sql = "SELECT us.*
            FROM users_school us
            LEFT JOIN users_school us1 ON us1.id = us.student_users_school_id
            WHERE us.user_id = $parent_id
            AND us1.user_id = $student_id";
    $res = query($sql,__LINE__);

    # If the status of any of these rows is still "Current," update the status to former and add an exit date
    if( ! empty($res) ){
        foreach($res as $value){
            if( $value['status_id'] != 10 ){
                if($value['entry_date'] > date("Y-m-d") ){
                    $sql = "UPDATE users_school SET status_id = 10, entry_date = CURDATE(), exit_date = CURDATE() WHERE id = '{$value['id']}' ";
                }
                else{
                    $sql = "UPDATE users_school SET status_id = 10, exit_date = CURDATE() WHERE id = '{$value['id']}' ";
                }
                query($sql,__LINE__);
            }
        }
    }

    # Delete from parent_student
    query("delete from parent_student where id = $id",__LINE__);
    echo '1';
}

/*
 * unlink parent and student (from student directory)
 */
if($object == 'staff_directory_students_directory_inner'){

    # Update users_school rows linking this parent and student to status of former
    # This is so that the parent stays in the parent directory in a passive way

    # First get the user_id of the parent we are unlinking
    $sql = "SELECT parent_id, student_id FROM parent_student WHERE id = $id";
    $res = query($sql,__LINE__);
    $row = array_pop($res);
    $parent_id = $row['parent_id'];
    $student_id = $row['student_id'];

    # Now get users_school rows linking this parent and student
    $sql = "SELECT us.*
            FROM users_school us
            LEFT JOIN users_school us1 ON us1.id = us.student_users_school_id
            WHERE us.user_id = $parent_id
            AND us1.user_id = $student_id";
    $res = query($sql,__LINE__);

    # If the status of any of these rows is still "Current," update the status to former and add an exit date
    if( ! empty($res) ){
        foreach($res as $value){
            if( $value['status_id'] != 10 ){
                if($value['entry_date'] > date("Y-m-d") ){
                    $sql = "UPDATE users_school SET status_id = 10, entry_date = CURDATE(), exit_date = CURDATE() WHERE id = '{$value['id']}' ";
                }
                else{
                    $sql = "UPDATE users_school SET status_id = 10, exit_date = CURDATE() WHERE id = '{$value['id']}' ";
                }
                query($sql,__LINE__);
            }
        }
    }

    # Delete from parent_student
    query("delete from parent_student where id = $id",__LINE__);
    echo '1';
}

/*
 * period bells
 */
if($object == 'period_bells_period_bells_inner'){
    query("delete from periods_bells where id = $id",__LINE__);
    echo '1';
}

/*
 * daily memos
 */
if($object == 'daily_memo_memos'){
    query("delete from daily_memos where id = $id",__LINE__);
    echo '1';
}

/*
 * expectations
 */
if($object == 'expectations_expectations'){
    query("delete from expectations where id = $id",__LINE__);
    echo '1';
}

/*
 * announcements
 */
if($object == 'announcements_sec_announcements'){
    query("delete from announcements_sec where id = $id",__LINE__);
    echo '1';
}

/*
 * boards_boards
 */
if($object == 'boards_boards'){
    query("delete from boards where id = $id",__LINE__);
    echo '1';
}


/*
 * topics_topics
 */
if($object == 'topics_topics'){
    query("delete from topics where id = $id",__LINE__);
    echo '1';
}

/*
 * Groups Widget
 *
 * This deletes the group and ALL of the group members.
 *
 */
if($object == 'groups_groups'){
    query("delete from group_users where group_id = $id",__LINE__);
    query("delete from groups where id = $id LIMIT 1",__LINE__);
    echo '1';
}
if($object == 'groups_groups_inner'){
    query("delete from group_users where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * Groups Members Widget (on department page)
 *
 */
if($object == 'group_members_members'){
    query("delete from group_users where id = $id LIMIT 1",__LINE__);
    echo '1';
}

/*
 * messaging_messages
 */
if($object == 'messaging_messages'){
    query("delete from messages where id = $id",__LINE__);
    echo '1';
}

/*
 * messaging_messages
 */
if($object == 'messaging_sent_messages'){
    query("delete from messages_sent_inbox where id = $id",__LINE__);
    echo '1';
}

/*
 * messaging_auto_messages
 */
if($object == 'messaging_auto_messages'){
    query("delete from messages_auto where id = $id",__LINE__);
    echo '1';
}
/*
 * service_hours_service_hours
 */
if($object == 'service_hours_service_hours'){
    query("delete from service_hours where id = $id",__LINE__);
    echo '1';
}
/*
 * allergies_allergies
 */
if($object == 'allergies_allergies'){
    query("delete from allergies where id = $id",__LINE__);
    echo '1';
}

/*
 * Badges
 */
if($object == 'badge_catalog_badges'){
    query("delete from badges_requirements where badge_id = $id",__LINE__);
    query("delete from badges where id = $id",__LINE__);
    echo '1';
}

/*
 * Badge Requirements
 */
if($object == 'badge_catalog_badges_inner'){
    query("delete from badges_requirements where id = $id",__LINE__);
    echo '1';
}

/*
 * EPIMS Work Assignment Records
 */
if($object == 'epims_work_assignment_record'){
    query("delete from ma_epims_work_assignments where id = $id",__LINE__);
    echo '1';
}

/*
 * Standards Collections
 */
if($object == 'standards_collection_collections'){
    $sql = "SELECT * FROM standards_collection WHERE id = $id";
    $result = query($sql,__LINE__);
    $row = array_pop($result);
    $collection_district_id = $row['district_id'];
    if($collection_district_id != $School->district_id){
        query("delete from standards_collection_district where collection_id = $id AND district_id = $School->district_id",__LINE__);
        echo '1';
    }
    else {
        query("delete from standards_collection_district where collection_id = $id",__LINE__);
        query("delete from standards_collection where id = $id",__LINE__);
        echo '1';
    }
}

/*
 * Standards Subjects
 */
if($object == 'standards_collection_collections_inner'){
    query("delete from standards_subjects where id = $id",__LINE__);
    echo '1';
}

/*
 * Standards Standards
 */
if($object == 'standards_categories_categories_inner'){

    # Check if this standard is used in any courses
    $sql = "SELECT * FROM courses_standards WHERE standard_id = $id";
    $res = query($sql,__LINE__);

    if($res) {
        echo 0;
    }
    else {
        query("delete from standards_standards where id = $id",__LINE__);
        echo '1';
    }
}

/*
 * Grade Levels / Periods / Standards
 */
if($object == 'standards_levels_periods_standards'){
    query("delete from grade_levels_periods_standards where id = $id",__LINE__);
    echo '1';
}

/*
 * Other Contacts
 */
if($object == 'other_contacts_contacts'){
    query("delete from other_contacts where id = $id",__LINE__);
    echo '1';
}

/*
 * Section Enrollment
 */
if($object == 'my_section_enrollment_sections'){
    global $_SES;

    # Notify teacher
    # Get user_id
    $res = query("select user_id, section_id from schedule where id = $id",__LINE__);
    $row = array_pop($res);
    $user_id = $row['user_id'];
    $section_id = $row['section_id'];

    # Get user's name to email teacher
    $res = query("SELECT concat(u.first_name,' ',u.last_name) as name FROM users u WHERE u.id = $user_id",__LINE__);
    $row = array_pop($res);
    $user_name = $row['name'];

    # Get the teacher(s) of this class, email addresses, and class name
    $sql = "SELECT u.id, u.first_name, con.value as email_address, concat(c.short_name,', Sec. ',sec.section_num) as section_name
            FROM schedule sched
            JOIN users u ON u.id = sched.user_id
            JOIN contact con ON con.user_id = sched.user_id AND con.field = 'email' AND con.primary_contact = 1
            JOIN courses c ON c.id = sched.course_id
            JOIN sections sec ON sec.id = sched.section_id
            WHERE sched.is_teacher = 1
            AND sched.section_id = $section_id";
    $teachers = query($sql,__LINE__);

    if( !empty($teachers) ) {
        foreach($teachers as $teacher_id => $teacher) {

            $teacher_name = $teacher['first_name'];
            $email_address = $teacher['email_address'];
            $section_name = $teacher['section_name'];

            # Email Teacher(s)
            $body = "Dear $teacher_name,<br><br>The following students have been dropped from your class: $section_name.<br><br>$user_name<br>MIDAS Education";

            if( ! empty($email_address) ) {
                $m = new SimpleEmailServiceMessage();
                $m->addTo($email_address);
                $m->setFrom('MIDAS Education <no-reply@midaseducation.com>');
                $m->setSubject('Notice of Dropped Enrollment');
                $m->setMessageFromString('', $body);
                $_SES->sendEmail($m);
            }
        }
    }

    query("delete from schedule where id = $id",__LINE__);
    echo '1';
}

/*
 * Scheduled Reports
 */
if($object == 'schedule_reports_scheduled_reports'){
    query("delete from report_recipients where report_schedule_id = $id",__LINE__);
    query("delete from report_schedule where id = $id",__LINE__);
    echo '1';
}

/*
 * Report Recipients
 */
if($object == 'schedule_reports_scheduled_reports_inner'){
    query("delete from report_recipients where id = $id",__LINE__);
    echo '1';
}

/*
 * Historical Grade Levels
 */
if($object == 'enrollment_history_history_inner'){
    query("delete from grade_levels_historical where id = $id",__LINE__);
    echo '1';
}

/*
 * Maintenance Queue Request Types
 */
if($object == 'maintenance_queue_types_maintenance_types'){
    query("delete from maintenance_q_types where id = $id",__LINE__);
    echo '1';
}
/*
 * Maintenance Queue Actions Taken
 */
if($object == 'maintenance_queue_maintenance_queue_inner'){
    query("delete from maintenance_q_actions where id = $id",__LINE__);
    echo '1';
}
// Ticketing group from type
if($object == 'maintenance_queue_types_maintenance_types_inner'){
    query("delete from maintenance_q_types_groups where id = $id",__LINE__);
    echo '1';
}
/*
 * Calendars widget
 */
if($object == 'calendars_district_calendar'){
	query("delete from calendars where id = $id and district_id='{$School->district_id}' limit 1",__LINE__);
	echo '1';
}else if($object == 'calendars_school_calendar'){
	query("delete from calendars where id = $id and school_id='{$School->id}' and district_id='{$School->district_id}' limit 1",__LINE__);
	echo '1';
}

/*
 * Services
 */
if($object == 'services_services'){
    $sql = "SELECT * FROM services_provided WHERE service_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo 0;
    }
    else{
        query("delete from services where id = $id limit 1",__LINE__);
        echo '1';
    }
}
if($object == 'my_services_services'){
    query("delete from services_provided where id = $id limit 1",__LINE__);
    echo '1';
}
/*
 * Units: Pacing Guide Tasks
 */
if($object == 'units_tasks'){
    query("delete from units_pacing_guides where id = $id",__LINE__);
    echo '1';
}
/*
 * Units: Standards
 */
if($object == 'units_standards'){
    query("delete from units_standards where id = $id",__LINE__);
    echo '1';
}
/*
 * Units: Resources
 */
if($object == 'units_resources'){
    query("delete from units_resources where id = $id",__LINE__);
    echo '1';
}
/*
 * Units: Outcomes
 */
if($object == 'units_outcomes_inner'){
    query("delete from units_outcomes where id = $id",__LINE__);
    echo '1';
}
/*
 * Units: Materials
 */
if($object == 'units_materials'){
    query("delete from units_materials where id = $id",__LINE__);
    echo '1';
}
/*
 * Units: Textbooks
 */
if($object == 'units_books'){
    query("delete from units_textbooks where id = $id",__LINE__);
    echo '1';
}
/*
 * Lessons: Standards
 */
if($object == 'lessons_standards'){
    query("delete from lessons_standards where id = $id",__LINE__);
    echo '1';
}
/*
 * Lessons: Understandings
 */
if($object == 'lessons_understandings'){
    query("delete from lessons_understandings where id = $id",__LINE__);
    echo '1';
}
/*
 * Lessons: Objectives
 */
if($object == 'lessons_objectives'){
    query("delete from lessons_objectives where id = $id",__LINE__);
    echo '1';
}
/*
 * Lessons: Resources
 */
if($object == 'lessons_resources'){
    query("delete from lessons_resources where id = $id",__LINE__);
    echo '1';
}
/*
 * Lessons: Materials
 */
if($object == 'lessons_materials'){
    query("delete from lessons_materials where id = $id",__LINE__);
    echo '1';
}
/*
 * Left Nav: Delete Groups
 */
if($object == 'district_nav_edit_navs_inner'){
    query("delete from permissions_left_nav where id = $id",__LINE__);
    echo '1';
}
/*
 * Sending District FTE
 */
if($object == 'sending_district_fte_sending_district_fte'){
    query("delete from sending_district_fte where id = $id",__LINE__);
    echo '1';
}
/*
 * Promotion Set-Up
 */
if($object == 'promotion_grades'){
    query("delete from promotion where id = $id",__LINE__);
    echo '1';
}
/*
 * Busing
 */
# Drivers
if($object == 'bus_drivers_bus_drivers'){
    query("delete from bus_routes_drivers where driver_id = $id",__LINE__);
    query("delete from bus_drivers where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Stops
if($object == 'bus_stops_bus_stops'){
    query("delete from bus_routes_stops where stop_id = $id",__LINE__);
    query("delete from bus_stops where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Routes
if($object == 'bus_routes_bus_routes'){
    query("delete from bus_routes_stops where route_id = $id",__LINE__);
    query("delete from bus_routes_drivers where route_id = $id",__LINE__);
    query("delete from bus_routes_school where route_id = $id",__LINE__);
    query("delete from bus_routes where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Stops from Routes
if($object == 'bus_routes_bus_stops'){
    query("delete from bus_routes_stops where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Riders from Routes
if($object == 'bus_routes_bus_stops_inner'){
    query("delete from bus_routes_riders where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Routes from Schools
if($object == 'bus_routes_bus_routes_school'){
    query("delete from bus_routes_school where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Stops from Student Portal
if($object == 'my_transportation_bus_routes'){
    query("delete from bus_routes_riders where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Lunch menus
if($object == 'lunch_menu_menus'){
    query("delete from lunch_menus where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Lunch free and reduced
if($object == 'lunch_free_reduced_frl'){
    # Get the user's ID
    $res = query("SELECT user_id FROM lunch_free_reduced WHERE id = $id",__LINE__);
    $row = array_pop($res);
    $user_id = $row['user_id'];

    query("delete from lunch_free_reduced where id = $id LIMIT 1",__LINE__);
    if($School->state_id == 22){
        query("UPDATE sims_users SET low_income_id = 1 WHERE user_id = $user_id LIMIT 1",__LINE__);
    }
    echo '1';
}
# Site page editors
if($object == 'acl_site_pages_site_page_editors'){
    query("update users_school set is_site_editor = 0 where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Removing is_admin
if($object == 'acl_is_admin_district_users'){
    query("update users set is_admin = 0 where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Grade book colors
if($object == 'gradebook_colors_color'){
    query("delete from gradebook_colors where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# my_attendance times
if($object == 'my_attendance_times'){
    query("delete from att_arrival_dismissal where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Report permissions
if($object == 'reports_permissions_reports_inner'){
    query("delete from report_permissions where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# User enrollment records
if($object == 'enrollment_history_history'){

    # Get the user's id
    $sql = "SELECT user_id, school_id FROM users_school WHERE id = $id";
    $res = query($sql,__LINE__);
    $row = array_pop($res);
    $user_id = $row['user_id'];
    $school_id = $row['school_id'];

    # Check whether the user has a second enrollment at this school separate from the one we are deleting
    $sql = "SELECT id FROM users_school WHERE user_id = $user_id and school_id = $school_id AND id NOT IN ($id)";
    $res = query($sql,__LINE__);

    if( empty($res) ) {
        # Delete assigned_to record for this user in this school
        query("delete from assigned_to where user_id = $user_id and school_id = $school_id LIMIT 1",__LINE__);

        # Delete group memberships at groups in this school
        query("delete from group_users where user_id = $user_id and group_id IN (select id from groups where school_id = $school_id)",__LINE__);

        # Delete locker assignments at this this school
        query("delete from lockers_users where user_id = $user_id and school_id = $school_id",__LINE__);

        # Delete section enrollments at this school
        query("delete from schedule where user_id = $user_id and school_id = $school_id",__LINE__);

        # Delete attendance data at this school
        query("delete from attendance where user_id = $user_id and school_id = $school_id",__LINE__);
    }

    # Delete parents' school records associated with this child's school enrollment
    query("delete from users_school where student_users_school_id = $id",__LINE__);

    # Delete the actual school enrollment record
    query("delete from users_school where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# User employment records
if($object == 'employment_history_history'){

    # Get the user's id
    $sql = "SELECT user_id, school_id FROM users_school WHERE id = $id";
    $res = query($sql,__LINE__);
    $row = array_pop($res);
    $user_id = $row['user_id'];
    $school_id = $row['school_id'];

    # Delete group memberships at groups in this school
    query("delete from group_users where user_id = $user_id and group_id IN (select id from groups where school_id = $school_id)",__LINE__);

    # Delete section teaching assignments at this school
    query("delete from schedule where user_id = $user_id and school_id = $school_id and is_teacher = 1",__LINE__);

    # Delete attendance data at this school
    query("delete from attendance where user_id = $user_id and school_id = $school_id",__LINE__);

    # Delete the actual school enrollment record
    query("delete from users_school where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Timesheet Programs
if($object == 'timesheet_programs_timesheet_programs'){
    $sql = "SELECT id FROM timesheets WHERE program_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else {
        query("delete from timesheet_programs where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}
# Standards from courses and child sections
if($object == 'standards_courses_standards'){

    # Get the course id
    $sql = "SELECT course_id, standard_id, grading_period_id FROM courses_standards WHERE id = $id";
    $res = query($sql,__LINE__);
    $row = array_pop($res);
    $course_id = $row['course_id'];
    $standard_id = $row['standard_id'];
    $grading_period_id = $row['grading_period_id'];

    # Get the year_id
    $sql = "SELECT year_id FROM grading_periods WHERE id = $grading_period_id";
    $res = query($sql,__LINE__);
    $row = array_pop($res);
    $year_id = $row['year_id'];

    # Get child courses
    $sql = "SELECT id FROM courses WHERE course_parent_id = $course_id AND dis_cal_yr_id = $year_id";
    $child_courses = query($sql,__LINE__);
    $child_courses  = array_map(function($item) { return $item['id']; }, $child_courses );
    $child_courses = implode(',',$child_courses);

    # Get section ids for these courses
    if( ! empty($child_courses) ) {
        $sql = "SELECT id FROM sections WHERE course_id IN ($course_id, $child_courses)";
    }
    else {
        $sql = "SELECT id FROM sections WHERE course_id = $course_id";
    }

    $sections = query($sql,__LINE__);
    $sections  = array_map(function($item) { return $item['id']; }, $sections );
    $sections = implode(',',$sections);

    if ( ! empty($child_courses) ) {
        query("DELETE FROM courses_standards
               WHERE course_id IN ($course_id, $child_courses)
               AND standard_id = $standard_id
               AND grading_period_id = $grading_period_id",__LINE__);
    }
    else {
        query("DELETE FROM courses_standards
               WHERE course_id = $course_id
               AND standard_id = $standard_id
               AND grading_period_id = $grading_period_id",__LINE__);
    }

    query("DELETE FROM assignment_categories
           WHERE section_id IN ($sections)
           AND standards_categories_id = $standard_id
           AND grading_period_id = $grading_period_id",__LINE__);

    query("DELETE FROM standards_grades
           WHERE section_id IN ($sections)
           AND standard_id = $standard_id
           AND grading_period_id = $grading_period_id",__LINE__);
    echo '1';
}

# Grading comments from school
if($object == 'grading_comments_grading_comments_school'){
    query("delete from grading_comments_school where id = $id LIMIT 1",__LINE__);
    echo '1';
}

# Course levels
if($object == 'course_levels_course_levels'){
    $sql = "SELECT id FROM courses WHERE course_level_id = $id";
    $result = query($sql,__LINE__);
    if($result){
        echo '0';
    }
    else {
        query("delete from course_levels where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
}
# Supervisors
if($object == 'supervisors_supervisors_inner'){
    query("delete from supervisors where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# HR Administrators
if($object == 'hr_administrators_site_page_editors'){
    query("delete from hr_administrators where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Course recommendations
if($object == 'course_recommendations_house_assignment_inner'){

    # Find out if the user is a scheduling administrator
    $res = query("SELECT id FROM scheduling_administrators WHERE user_id = $User->id AND district_id = $School->district_id",__LINE__);
    $is_administrator = empty($res) ? 0 : 1;

    if( $is_administrator ) {
        query("delete from courses_recommendations where id = $id LIMIT 1",__LINE__);
        echo '1';
    }
    else {
        # If not an administrator, we need to check if the user made the recommendation before deleting
        $res = query("SELECT id FROM courses_recommendations WHERE created_by = $User->id",__LINE__);
        if ( ! empty($res) ) {
            query("delete from courses_recommendations where id = $id LIMIT 1",__LINE__);
            echo '1';
        }
        else {
            echo '0';
        }
    }
}
# Scheduling Administrators
if($object == 'scheduling_administrators_site_page_editors'){
    query("delete from scheduling_administrators where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Orders from cart
if($object == 'my_cart_items') {

    // get order_id and price of current item
    $res = query('SELECT order_id, price, total_transaction_fee FROM items WHERE id = ' . escape($id), __LINE__);
    $order_id = $res[0]['order_id'];
    $price = $res[0]['price'];
    $total_transaction_fee = $res[0]['total_transaction_fee'];

    query("DELETE FROM items WHERE id = $id LIMIT 1",__LINE__);

    //  check if any order exists for that order_id, if not then delete the order record also
    $res = query('SELECT count(id) total FROM items WHERE order_id = ' . $order_id, __LINE__);
    if ($res[0]['total'] == 0) {
        query("DELETE FROM orders WHERE id = $order_id LIMIT 1",__LINE__);
    } else {
        // update price of current order
        query('UPDATE orders SET
                    transaction_fee = transaction_fee - '. $total_transaction_fee .'
                    ,price = price - ' . $price . '
               WHERE id = ' . $order_id . ' LIMIT 1', __LINE__);
    }
    echo '1';
}
# Discounts
if($object == 'discounts_discounts'){
    query("delete from discounts where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Collaborative attendance report districts
if($object == 'attendance_report_sending_districts'){
    query("delete from tec_sending_districts where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Course request options
if($object == 'course_requests_admin_request_options'){

    //  check if any requests already exist for this option
    $res = query('SELECT id FROM courses_requests WHERE option_id = ' . $id, __LINE__);
    if ( ! empty($res) ) {
        echo '0';
    }
    else {
        query("DELETE FROM courses_requests_options WHERE id = $id LIMIT 1",__LINE__);
        query("DELETE FROM courses_requests_options_courses WHERE option_id = $id",__LINE__);
        echo '1';
    }
}
# Course requests
if($object == 'my_course_requests_requests'){
    query("delete from courses_requests where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Discount items
if($object == 'discounts_discounts_inner'){
    query("delete from discounts_items where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Credit courses
if($object == 'credits_credits_inner'){
    query("delete from credits_courses where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Houses / teams members
if($object == 'houses_teams_groups_inner'){
    query("delete from group_users where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Teacher per-term FTE
if($object == 'teacher_fte_teacher_time_slots_inner'){
    query("delete from teacher_fte where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# IEP Dates
if($object == 'my_services_iep_info'){
    query("delete from iep_dates where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# BICO behavior incidents
if($object == 'incident_submit_bico_incidents'){
    query("delete from behavior_incidents_bico where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Teacher time slots
if($object == 'teacher_availability_teacher_time_slots_inner'){
    query("delete from teacher_timeslots where id = $id LIMIT 1",__LINE__);
    echo '1';
}
# Remove Tab
if ( $object == 'widgets_and_tabs_remove_tab' ) {
    query("delete from widget_groups_default where id = $id limit 1", __LINE__);
    echo 1;
}
# Delete scheduled report
if ( $object == 'reports_schedule_scheduled_reports' ) {
    query("delete from report_recipients where report_schedule_id = $id", __LINE__);
    query("delete from report_schedule where id = $id limit 1", __LINE__);
    echo 1;
}
# Delete scheduled report recipient
if ( $object == 'reports_schedule_scheduled_reports_inner' ) {
    query("delete from report_recipients where id = $id", __LINE__);
    echo 1;
}
# Delete attendance track
if ( $object == 'attendance_tracks_tracks' ) {
    query("delete from att_tracks_users where track_id = $id", __LINE__);
    query("delete from att_tracks where id = $id", __LINE__);
    echo 1;
}
# Delete user from attendance track
if ( $object == 'attendance_tracks_tracks_inner' ) {
    query("delete from att_tracks_users where id = $id", __LINE__);
    echo 1;
}
# Delete intervention
if ( $object == 'interventions_admin_interventions' ) {
    query("delete from interventions_users where intervention_id = $id", __LINE__);
    query("delete from interventions where id = $id", __LINE__);
    echo 1;
}
# Delete user from intervention
if ( $object == 'interventions_admin_interventions_inner' ) {
    query("delete from interventions_users where id = $id", __LINE__);
    echo 1;
}
# Delete standard from user
if ( $object == 'standards_students_standards' ) {
    query("delete from standards_users where id = $id", __LINE__);
    echo 1;
}
# Delete test from district
if ( $object == 'tests_admin_tests' ) {
    query("delete from tests_districts where id = $id", __LINE__);
    echo 1;
}
# Delete intervention from student log
if ( $object == 'my_interventions_log_logs' ) {
    query("delete from interventions_log where id = $id", __LINE__);
    echo 1;
}
# Delete track rule
if ( $object == 'attendance_tracks_track_rules' ) {
    query("delete from att_tracks_users_bulk where id = $id", __LINE__);
    echo 1;
}
# Delete saved parameters
if ( $object == 'report_saved_parameters_parameters' ) {
    query("delete from report_saved_parameters where id = $id", __LINE__);
    echo 1;
}
# Delete eoy benchmark
if ( $object == 'tests_benchmarks_benchmarks' ) {
    query("delete from tests_benchmarks_eoy where id = $id", __LINE__);
    echo 1;
}
# Delete grading period benchmark
if ( $object == 'tests_benchmarks_benchmarks_inner' ) {
    query("delete from tests_benchmarks where id = $id", __LINE__);
    echo 1;
}
# Delete a standard from a progression
if ( $object == 'standards_progression_standards' ) {

    $res = query("SELECT sort_order, progression_id FROM standards_progression_standards WHERE id = $id",__LINE__);
    $row = array_pop($res);

    query("delete from standards_progression_standards where id = $id", __LINE__);
    query("update standards_progression_standards
           set sort_order = (sort_order - 1)
           where sort_order > {$row['sort_order']}
           and progression_id = {$row['progression_id']}",__LINE__);
    echo 1;
}
# Delete leave reason
if ( $object == 'leave_requests_reasons_reasons' ) {
    query("delete from leave_requests where reason_id = $id", __LINE__);
    query("delete from leave_requests_allowed where reason_id = $id", __LINE__);
    query("delete from leave_requests_reasons where id = $id", __LINE__);
    echo 1;
}
# Delete bus provider
if ( $object == 'bus_providers_providers' ) {
    query("delete from bus_providers where id = $id", __LINE__);
    echo 1;
}
# Delete intervention assignments
if ( $object == 'my_interventions_interventions' ) {
    query("delete from interventions_users where id = $id", __LINE__);
    echo 1;
}
# Delete response assignments
if ( $object == 'my_responses_responses' ) {
    $res = query("SELECT response_id, user_id FROM interventions_responses_users WHERE id = $id",__LINE__);
    $row = array_pop($res);

    query("delete from interventions_responses_users_measures where response_id = {$row['response_id']} and user_id = {$row['user_id']}",__LINE__);
    query("delete from interventions_responses_users where id = $id", __LINE__);
    echo 1;
}
# Delete response measure/goal
if ( $object == 'my_responses_responses_inner' ) {
    query("delete from interventions_responses_users_measures where id = $id", __LINE__);
    echo 1;
}
# Delete copying attendance period to period rules
if ( $object == 'attendance_period_rules_periods' ) {
    query("delete from att_copy_period_to_period where id = $id", __LINE__);
    echo 1;
}
# Delete intervention from intervention_trigger
if ( $object == 'interventions_triggers_triggers_inner' ) {
    query("delete from interventions_triggers_interventions where id = $id", __LINE__);
    echo 1;
}
# Delete intervention_trigger
if ( $object == 'interventions_triggers_triggers' ) {
    query("delete from interventions_triggers_interventions where trigger_id = $id", __LINE__);
    query("delete from interventions_triggers where id = $id", __LINE__);
    echo 1;
}
# Delete member from activity
if ( $object == 'activities_activities_inner' ) {
    query("delete from group_users where id = $id", __LINE__);
    echo 1;
}
# Delete user from tutorial completion
if ( $object == 'tutorial_tutorial_inner' ) {
    query("delete from tutorial_users_widgets where id = $id", __LINE__);
    echo 1;
}
# Delete course request from schedule set up
if ( $object == 'courses_schedule_sections_requests_inner' ) {
    query("delete from courses_requests where id = $id", __LINE__);
    echo 1;
}
# Delete course request from course details in schedule set up
if ( $object == 'courses_schedule_sections_requests' ) {
    query("delete from courses_requests where id = $id", __LINE__);
    echo 1;
}
# Delete all requests from course set-up
if ( $object == 'courses_schedule_sections_courses' ) {
    query("delete from courses_requests where course_id = $id", __LINE__);
    echo 1;
}
# Delete a section in schedule set up
if ( $object == 'courses_schedule_sections_courses_inner' ) {

    // If this is a piece of a multi-part course, delete all parts
    $sql = "SELECT id
            FROM sections_scheduling
            WHERE course_id
            IN
            (SELECT id
            FROM courses_split_by_term
            WHERE course_id IN
            (SELECT course_id
            FROM courses_split_by_term
            WHERE id IN (SELECT course_id FROM sections_scheduling WHERE id = $id)))
            AND
            section_num IN (SELECT section_num FROM sections_scheduling WHERE id = $id)
            ";
    $ids_to_delete = query($sql,__LINE__);

    if( !empty($ids_to_delete) ) {
        $ids_to_delete = array_map(function($item) { return $item['id']; }, $ids_to_delete);
        query("DELETE FROM sections_scheduling WHERE id IN (" . implode(',', $ids_to_delete) . ")", __LINE__);
        query("DELETE FROM sections_scheduling_terms WHERE sec_sched_id IN (" . implode(',', $ids_to_delete) . ")", __LINE__);
        query("DELETE FROM sections_scheduling_teachers WHERE sec_sched_id IN (" . implode(',', $ids_to_delete) . ")", __LINE__);
    }
    echo 1;
}