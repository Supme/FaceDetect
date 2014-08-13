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

        $this->getImage($this->config['foto'].DIRECTORY_SEPARATOR.$fotoName.'.jpg');

        // ToDo запись в базу
        // А пока просто тест

        // Автоматически определяем морду лица, вырезаем, расширяем, поворачиваем
        $autoimage = $this->autoImage($this->config['foto'].DIRECTORY_SEPARATOR.$fotoName.'.jpg');
        imagejpeg($autoimage, $this->config['foto'].DIRECTORY_SEPARATOR.$fotoName.'_autocrop.jpg');

        $masks = $this->getMasks();

        foreach($masks as $key => $mask){
            $image = $this->createImage(
                $autoimage, // detected face
                $mask['file'], // poster for face
                $mask['width'], // width face
                $mask['angle'], //angle face
                $mask['x'], // x position face
                $mask['y'] // y position face
            );
            imagejpeg($image, $this->config['foto'].DIRECTORY_SEPARATOR.$fotoName.'_'.$key.'.jpg');
        }

        return $formId;
    }

    private function getMasks(){
        $cacheFile = $this->config['masks'].DIRECTORY_SEPARATOR.'masks.json';
        if (is_readable($cacheFile)){
            $masks = json_decode(file_get_contents($cacheFile), true);
        } else {
            $masks = [];

            foreach(glob($this->config['masks'].DIRECTORY_SEPARATOR.'*png') as $file){
                $params = explode('_', $file);
                $dbg[] = $file;
                if(count($params) == 5){
                    $masks[str_replace($this->config['masks'].DIRECTORY_SEPARATOR, '', $params[0])] = [
                        'file' => $file,
                        'width' => $params[1],
                        'angle' => $params[2],
                        'x' => $params[3],
                        'y' => $params[4]
                    ];
                }

            };
            //file_put_contents('debug.txt', var_export($dbg,true).var_export($masks,true));
            file_put_contents($cacheFile, json_encode($masks));
        }

        return $masks;
    }
    public function isFace(){
        $result = false;
        $tmpFile = $this->config['tmp_dir'].DIRECTORY_SEPARATOR.$this->config['sessionId'].'.jpg';
        $this->getImage($tmpFile);
        if ($face = $this->faceDetect($tmpFile)){
            // Если морда достаточно высока и широка, она нам подходит
            if($face['w'] > $this->config['faceWidth']/100 and $face['h'] > $this->config['faceHeight']/100){
                $result = true;
            }
        }
        return $result;
    }

    static public function getRandom($size){
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
            $result['w'] = $faceCoor[2];
            $result['h'] = $faceCoor[3];
        } else {
            $result = false;
        }

        return $result;
    }


// Хелперы для работы с изображениями

    private function autoImage($file){
        $detect = $this->faceDetect($file);

        $face = imagecreatefromjpeg($file);

        $x = imagesx($face);
        $y = imagesy($face);
        //Реально определенное
        $fromX = $x * $detect['x'];
        $fromY = $y * $detect['y'];
        $detectWidth = $x * $detect['w'];
        $detectHeight = $y * $detect['h'];
        $toX = $fromX + $detectWidth;
        $toY = $fromY + $detectHeight;
        //Увеличим высоту, чтоб добавить лоб и подбородок, который обычно обрезается в OpenCV
        $fromY = $fromY - ($detectHeight * 0.10);
        $toY = $toY + ($detectHeight * 0.20);

        $face = $this->cropImage($face, $fromX, $fromY, $toX, $toY);

        return $face;
    }

    private function createImage($image, $poster, $width, $angle, $positionX, $positionY){
        $height = imagesy($image) * ($width/imagesx($image));
        $image = $this->resizeImage($image, $width, $height);
        $image = $this->rotateImage($image, $angle);
        $image = $this->mergeImage(
            $image,
            imagecreatefrompng($poster),
            $positionX,
            $positionY
        );

        return $image;
    }

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

    private function mergeImage($imageBottom, $imageTop, $xBottom=0, $yBottom=0, $xTop=0, $yTop=0){
        $imageMerge = imagecreatetruecolor(imagesx($imageTop), imagesy($imageTop));

        // Сохраняем альфа-канал.
        imagesavealpha($imageTop, true);

        // Накладываем второе изображение на первое с нужным смещением
        imagecopy($imageMerge, $imageBottom, $xBottom, $yBottom, 0, 0, imagesx($imageBottom), imagesy($imageBottom));
        imagecopy($imageMerge, $imageTop, $xTop, $yTop, 0, 0, imagesx($imageTop), imagesy($imageTop));

        return $imageMerge;

    }

}
