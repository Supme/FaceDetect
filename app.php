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

session_start();
if(!isset($_SESSION['id'])){
    $_SESSION['id'] = application::getRandom(20);
}

// Временный файл
$config['tmp_file'] = 'tmp/'.$_SESSION['id'].'.jpg';

$app = new application($config);

if(isset($_POST['detectFace'])){
    $result['isFace'] = $app->isFace();
}
if(isset($_POST['flashFace'])){
    $result = $app->flashFace();
}
if(isset($_POST['sendForm'] )){
    $result = $app->sendForm();
}

// Выдаем результат
header('Content-Type: application/json');
echo json_encode($result);
