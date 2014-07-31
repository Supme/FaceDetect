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

        $result['status'] = true;

        return $result;
    }

    public function flashFace(){

        $formId = $this->getRandom(5);

        $fotoName = 'photo/'.$this->getRandom(10).'.jpg';

        // ToDo запись в базу

        $this->getImage($fotoName);

        $result['formId'] = $formId;

        return $result;
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

    public function getRandom($size){
        $chars="qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP";
        $lenght=StrLen($chars)-1;
        $result=null;
        while($size--)
            $result.=$chars[rand(0,$lenght)];

        return $result;
    }

}