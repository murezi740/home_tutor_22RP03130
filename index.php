<?php
include 'config.php';
include 'menu.php';

$sessionId   = $_POST['sessionId'];
$phoneNumber = $_POST['phoneNumber'];
$serviceCode = $_POST['serviceCode'];
$text        = $_POST['text'];


$isRegistered = false;
if ($conn) {
    $stmt = $conn->prepare("SELECT id FROM parents WHERE phone_number = ?");
    if ($stmt) {
        $stmt->bind_param("s", $phoneNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $isRegistered = $result->num_rows > 0;
        $stmt->close();
    }
}

$menu = new Menu();
$text = $menu->middleware($text);

if ($text == "" && !$isRegistered) {
    $menu->mainMenuUnregistered();
} else if ($text == "" && $isRegistered) {
    $menu->mainMenuRegistered();
} else if (!$isRegistered) {
    $textArray = explode("*", $text);
    switch ($textArray[0]) {
        case 1:
            $menu->menuRegister($textArray, $phoneNumber);
            break;
        default:
            echo "END Invalid option, Retry";
    }
} else {
    $textArray = explode("*", $text);
    switch ($textArray[0]) {
        case 1:
            $menu->menuChooseTeacher($textArray, $phoneNumber);
            break;
        case 2:
            $menu->menuViewChosenTeachers($textArray, $phoneNumber);
            break;
        case 3:
            echo "END Going back to previous menu";
            break;
        case 4:
            echo "END Returning to main menu";
            break;
        default:
            echo "END Invalid choice\n";
    }
} 