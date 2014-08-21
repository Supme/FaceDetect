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

include_once 'config.php';
include_once 'application.php';
include_once 'libs/PHPMailer/PHPMailerAutoload.php';

if (!empty($_POST)){
    // Проверим есть ли у клиента id сессии
    session_start();

// ToDo тут сделать авторизацию для захвата лиц
    if(!isset($_SESSION['id'])){
        $_SESSION['id'] = application::getRandom(20);
    }

    $config['tmp_dir'] = $tmp_dir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
    $config['sessionId'] = $_SESSION['id'];

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
                $result['url'] = 'http://'.$_SERVER['HTTP_HOST'].'/';
                break;
            case 'sendForm':
                $result['status'] = $app->sendForm();
                break;
            case 'getPoster':
                $result = $app->getPoster($_POST['id']);
                break;
            case 'detectFace':
                $result['isFace'] = $app->isFace();
                break;
            default:
                $result['error'] = 'No such action';
        }

        // Выдаем результат
        header('Content-Type: application/json');
        echo json_encode($result);

    } else {
        if(isset($_POST['remoteForm'])){
            $result = $app->sendForm();
            if($result['status'] == 'success'){
                echo 'ok<pre>';
            } else {
                echo $result['status'].'<br/>';
                foreach($result['message'] as $message){
                    echo $message."<br/>\n";
                }
            }
        }
    }

} else {

    $formId = $_SERVER['QUERY_STRING'];
    $form = file_get_contents('./html/form.html');

    if($formId == 'face'){
        $html = file_get_contents('./html/face.html');
        $html = str_replace('{{formContent}}', $form, $html);
    } else {
        $html = file_get_contents('./html/after.html');
        $html = str_replace('{{formContent}}', $form, $html);
        $html = str_replace('{{formId}}', $formId, $html);
    }

    echo $html;

}