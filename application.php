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

class application {

    public function __construct($config){

        $this->config = $config;

    }

    public function sendForm(){


        return true;
    }

    public function flashFace(){

        $formId = $this->getRandom(5);

        $fotoName = $this->getRandom(10);

        $fotoFolder = 'foto';

        $this->getImage($fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'.jpg');

        // ToDo запись в базу
        // А пока просто тест

        $image = imagecreatefromjpeg($fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'.jpg');
        $image = $this->cropImage($image,120, 30, 420, 450);
        imagejpeg($image, $fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'_crop.jpg');
        $image = $this->resizeImage($image, 357, 500);
        imagejpeg($image, $fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'_resize.jpg');
        $image = $this->rotateImage($image,253);
        imagejpeg($image, $fotoFolder.DIRECTORY_SEPARATOR.$fotoName.'_rotate.jpg');

        return $formId;
    }

    public function isFace(){
        $this->getImage($this->config['tmp_file']);
        if ($face = $this->faceDetect($this->config['tmp_file'])){
            // Если морда достаточно высока и широка, она нам подходит
            if($face['w'] > $this->config['faceWidth']/100 and $face['h'] > $this->config['faceHeight']/100){
                return true;
            } else {
                return false;
            }
        }
    }

    public function getRandom($size){
        $chars="qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP";
        $lenght=StrLen($chars)-1;
        $result=null;
        while($size--)
            $result.=$chars[rand(0,$lenght)];

        return $result;
    }

    private function getImage($file){
        $data = $_POST['imgBase64'];
        $uri =  substr($data,strpos($data,",")+1);
        file_put_contents($file, base64_decode($uri));
    }

    private function faceDetect($file){
        $face = exec('./bin/find-face --cascade=./cascade/haarcascade_frontalface_default.xml '.$file);
        // Если OpenCV отдал нам 4 значения, значит он выполнился удачно
        if(count($faceCoor = explode(' ', $face)) == 4){
            $result['x'] = $faceCoor[0];
            $result['y'] = $faceCoor[1];
            $result['h'] = $faceCoor[2];
            $result['w'] = $faceCoor[3];
            return $result;
        } else {
            return false;
        }
    }


// Хелперы для работы с изображениями

    private function cropImage($image, $fromX, $fromY, $toX, $toY){
        $width = $toX - $fromX;
        $height = $toY - $fromY;
        $imageCroped = imagecreatetruecolor($width, $height);
        imagecopyresampled($imageCroped, $image, 0, 0, $fromX, $fromY, $width, $height, $width, $height);
        return $imageCroped;
    }

    private function resizeImage($image, $width, $height){
        $imageResized = imagecreatetruecolor($width, $height);
        imagecopyresampled($imageResized, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        return $imageResized;
    }

    private function rotateImage($image, $angle){
        return imagerotate($image, $angle, hexdec('FFFFFF'));
    }

}