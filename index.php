<?php
// Text header
header("Content-type: text/plain");

// Check url
if (!isset($_GET['webcal']) || !filter_var($_GET['webcal'], FILTER_VALIDATE_URL)) {
    header("HTTP/1.1 422 Unprocessable Entity");
    die("Malformed or no 'webcal' url query parameter given. Ensure it is a valid url.");
}

try {
    // Lib
    require_once("icalendar-master/zapcallib.php");

    // Util functions
    function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

    function endsWith($haystack, $needle)
    {
        $length = strlen($needle);

        return $length === 0 ||
            (substr($haystack, -$length) === $needle);
    }

    // Get the webcal
    try {
        $webcal = $_GET['webcal'];

        // Ensure http
        if (startsWith($webcal, "webcal://")) {
            $webcal = "http://" . explode("webcal://", $webcal, 2)[1];
        }
        if (startsWith($webcal, "https://")) {
            $webcal = "http://" . explode("https://", $webcal, 2)[1];
        }

        // Get content
        $webcal_content = @file_get_contents($webcal);
    } catch (Exception $e) {
        header("HTTP/1.1 500 Internal Server Error");
        die("An error occurred whilst fetching the webcal from the given url.");
    }
    if (!$webcal_content) {
        header("HTTP/1.1 500 Internal Server Error");
        die("An invalid response was received when fetching the webcal from the given url.");
    }

    // Make it an ical object
    try {
        $icalobj = new ZCiCal($webcal_content);
    } catch (Exception $e) {
        header("HTTP/1.1 500 Internal Server Error");
        die("An error occurred whilst parsing the webcal to a valid ical object.");
    }

    // Iterate over every event
    foreach ($icalobj->tree->child as $node) {
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

                    $node->data["DTSTART"]->parameter = ["value" => "DATE"];
                    $node->data["DTEND"]->parameter = ["value" => "DATE"];
                    $node->data["DTSTART"]->value = [$start_date];
                    $node->data["DTEND"]->value = [$end_date];
                }

            }
        }
    }

    // Add custom event (#ad)
    $root = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/';
    $eventobj = new ZCiCalNode("VEVENT", $icalobj->curnode);
    $eventobj->addNode(new ZCiCalDataNode("SUMMARY:Webcal Fixer"));
    $eventobj->addNode(new ZCiCalDataNode("DESCRIPTION:This calender is originally from (" . $_GET['webcal'] . ") and has been converted by Webcal Fixer (" . $root . ").\n\nThis project is created by Matt Cowley (https://mattcowley.co.uk/). (#ad)"));
    $eventobj->addNode(new ZCiCalDataNode("DTSTART;VALUE=DATE:" . date("Ymd")));
    $eventobj->addNode(new ZCiCalDataNode("DTEND;VALUE=DATE:" . date("Ymd")));
    $eventobj->addNode(new ZCiCalDataNode("URL:webcal://" . $_SERVER['HTTP_HOST'] . $_SERVER["REQUEST_URI"]));

    // Export new
    echo $icalobj->export();
    die();

} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    die("An error occurred whilst processing the webcal.");
}

?>