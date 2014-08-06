<?php
/**
 * @package public.
 * @author Supme
 * @copyright Supme 2014
 * @license http://opensource.org/licenses/MIT MIT License	
 *
 *  THE SOFTWARE AND DOCUMENTATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF
 *	ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 *	IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A PARTICULAR
 *	PURPOSE.
 *
 *	Please see the license.txt file for more information.
 *
 */

$config = [

    // Дддебб-ба-ба-баг нужен?
    'debug' => true,

    // Ширина и высота поля для захвата
    'width' => 640,
    'height' => 480,

    // Процент ширины и высоты морды в поле, означающий достаточное ее приближение к экрану
    'faceWidth' => 30,
    'faceHeight' => 30,

    // Время до вспышки
    'flashWait' => 5,

    // Сколько времени держать на экране форму если морда пропала из поля зрения
    'formWait' => 10,

];

//--------------------------------------------------------------

include_once 'application.php';

// Проверим есть ли у клиента id сессии
session_start();
// и если нет, создадим
if(!isset($_SESSION['id'])){
    $_SESSION['id'] = application::getRandom(20);
}

// Временный файл
$tmp_dir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
$config['tmp_file'] = $tmp_dir.DIRECTORY_SEPARATOR.$_SESSION['id'].'.jpg';

// закроем и запишем сессию, чтобы не блокировать доступ если клиент еще чего захочет попросить пока мы выполняемся
session_write_close();

$app = new application($config);

if(isset($_POST['action'])){
    $result = [];
    switch($_POST['action']){
        case 'detectFace':
            $result['isFace'] = $app->isFace();
            break;
        case 'flashFace':
            $result['formId'] = $app->flashFace();
            break;
        case 'sendForm':
            $result['status'] = $app->sendForm();
            break;
        default:
            $result['error'] = 'No such action';
    }

    // Выдаем результат
    header('Content-Type: application/json');
    echo json_encode($result);

} else {

    echo '<h2 style="text-align: center">Well, what are you like to see?</h2>';
}

