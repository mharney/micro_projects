<?php
/**
 * Created by PhpStorm.
 * User: bpotts
 * Date: 6/6/16
 * Time: 12:41 AM
 */

$teacher_sections = [
    [332757,"FL2201-1 French2 (23)",25095,"Jessica Allman",4,4,"1,2","A"],
    [332693,"FL2101-1 French1 (23)",25095,"Jessica Allman",2,2,"2,1","B"],
    [337145,"LA1700-1 StratLit (16)",25095,"Jessica Allman",3,3,"2,1","B"],
    [338041,"PE1107A/B-2 BoyPE (23)",25126,"Shawn Allred",1,1,1,"A"],
    [338040,"PE1107A/B-3 BoyPE (0)",25126,"Shawn Allred",1,1,2,"B"],
    [335727,"PE1107-1 BoyPE (30)",25126,"Shawn Allred",2,2,"2,1","B"],
    [335694,"PE1202-4 Fld/CrtSprt (25)",25126,"Shawn Allred",3,3,"2,1","B"],
    [334493,"SS1205-2 WrldHist9 (22)",25096,"Stephany Anderson",1,1,"2,1","B"],
    [336896,"SS1205-10 WrldHist9 (25)",25096,"Stephany Anderson",3,3,"2,1","B"]
];
foreach($teacher_sections as $k => $section) {
    $_days = strstr($section[6],",") !== false
        ? explode(",",$section[6]) : [$section[6]];
    $sections[$section[2]][$section[4]]
        = array(
        "days"=>$_days,
        "period_id"=>$section[4],
        "period_name"=>$section[7],
        "section"=>$section[1]);
    $schedule['teachers'][$section[2]]
        = array(
        "teacher"=>$section[3],
        "sections"=>$sections[$section[2]]);
}

echo "<pre>" . print_r($schedule,1) . "</pre>";

$html_view = "<table border='1' style='margin:0'><tr><th>Teacher Name</th>";

$schedule['periods'] = [0,1,2,3,4];
$schedule['days'] = [1=>'A',2=>'B'];
$schedule['no_days'] = 2;

# Period headings should span as many days as there are
foreach($schedule['periods'] as $period_id => $period) {
    $html_view .= "<th colspan='{$schedule['no_days']}'>$period</th>";
}

$html_view .= "</tr>";

# Add a day heading row under each period
$html_view .= "<tr><td>&nbsp;</td>";
foreach($schedule['periods'] as $period_id => $period) {
    foreach($schedule['days'] as $day_id => $day) {
        $html_view .= "<th>{$day}</th>";
    }
}
$html_view .= "</tr>";

$blank_cell = "<td style='background-color: #eee; min-width: 75px'>&nbsp;</td>";
foreach($schedule['teachers'] as $teacher_id => $teacher) {

    $html_view .= "<tr><td>{$teacher['teacher']}</td>";

    foreach($schedule['periods'] as $period_id => $period) {
        $skip_days = 0;
        foreach($schedule['days'] as $day_id => $day) {
            if($skip_days > 0) { $skip_days--; continue; }

            // for multi-day courses, span the first td, skip the rest
            if (!empty($teacher['sections'][$period_id])) {
                $_days = $teacher['sections'][$period_id]['days'];

                // place a blank cell if the section does not meet on this day
                if( ! in_array($day_id,$_days)){ //
                    $html_view .= $blank_cell;

                // show course cell, using colspan for multi-day courses
                } else {
                    $skip_days = count($_days) - 1;
                    $html_view .= "<td colspan=\"" . count($_days) . "\">{$teacher['sections'][$period_id]['section']}</td>";
                }
            } else {
                $html_view .= $blank_cell;
            }
        }
    }
    $html_view .= "</td>";
}

echo $html_view;