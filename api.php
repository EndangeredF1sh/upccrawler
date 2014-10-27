<?php
require_once('Include/PageReader.php');
require_once('Include/UpcRobot.php');

$method = $_POST['method'];
$id = $_POST['id'];
$password = $_POST['password'];

if (isset($method)) {
    $r = new UpcRobot();
    try {
        if ($r->login($id, $password)) {
            switch ($method) {
            case 'card':
                echo json_encode(array('status' => 'OK', 'result' => $r->getCardInfo()));
                break;
            case 'cardlog':
				echo json_encode(array('status' => 'OK', 'result' => $r->getCardLog($_POST['time1'], $_POST['time2'])));
				break;

            case 'library':
                echo json_encode(array('status' => 'OK', 'result' => $r->getBorrowedInfo()));
                break;

            case 'score':
                $term=isset($_POST['term'])?$_POST['term']:'';
                echo json_encode(array('status' => 'OK', 'result' => $r->getScore($term)));
                break;

            case 'classtable':
                if (isset($_POST['term']) && !empty($_POST['term'])) {
                    $term=$_POST['term'];
                    $tab = $r->getTable($term);
                    echo json_encode(array('status' => 'OK', 'result' => $tab->getTable(), 'memo' => $tab->getMemo()));
                } else {
                    throw new PageNotFoundException();
                }
                break;

            case 'classlist':
                if (isset($_POST['term']) && !empty($_POST['term'])) {
                    $term=$_POST['term'];
                    $tab = $r->getTable($term);
                    echo json_encode(array('status' => 'OK', 'result' => $tab->getList(), 'memo' => $tab->getMemo()));
                } else {
                    throw new PageNotFoundException();
                }
                break;

            default:
                echo json_encode(array('status' => 'OK'));
                break;
            }
        } else {
            echo json_encode(array('status' => 'FAILURE'));
        }
    } catch (PageNotFoundException $e) {
        echo json_encode(array('status' => 'ERROR'));
    }
} else {
    echo json_encode(array('status' => 'ERROR'));
}

?>
