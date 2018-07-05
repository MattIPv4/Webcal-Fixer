<?php
// Lib
require_once("icalendar-master/zapcallib.php");

// Util function
function endsWith($haystack, $needle)
{
    $length = strlen($needle);

    return $length === 0 ||
        (substr($haystack, -$length) === $needle);
}

// Text header
header("Content-type: text/plain");

// Get the webcal
$webcal = $_GET['webcal'];
$webcal_content = file_get_contents($webcal);

// Make it an ical object
$icalobj = new ZCiCal($webcal_content);

// Iterate over every event
foreach($icalobj->tree->child as $node) {
    if ($node->getName() == "VEVENT") {

        // Get the start and ends
        $start = $node->data["DTSTART"]->value[0];
        $end = $node->data["DTEND"]->value[0];

        // Check if "fake" all day
        if (endsWith($start, "T000000")) {
            if (endsWith($end, "T235959")) {

                // Update to all day event
                $start_date = explode("T", $start, 2)[0];
                $end_date = explode("T", $end, 2)[0];

                $node->data["DTSTART"]->parameter = ["value"=>"DATE"];
                $node->data["DTEND"]->parameter = ["value"=>"DATE"];
                $node->data["DTSTART"]->value = [$start_date];
                $node->data["DTEND"]->value = [$end_date];
            }

        }
    }
}

// Export new
echo $icalobj->export();

?>