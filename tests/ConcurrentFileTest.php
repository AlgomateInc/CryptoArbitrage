<?php

include_once('log4php/Logger.php');
require_once('ConcurrentFile.php');
/**
 * Created by PhpStorm.
 * User: marko_000
 * Date: 9/15/2015
 * Time: 9:05 PM
 */
class ConcurrentFileTest extends PHPUnit_Framework_TestCase
{
    public function testPerformMultipleReadWrite()
    {
        $cf = new ConcurrentFile('ConcurrentFileTest.txt');

        for($i = 0; $i<100; $i++)
        {
            $data = 'hello';
            $cf->write($data);
            $readData = $cf->read();

            $this->assertEquals($data, $readData);
        }
    }

    public function testPerformMultipleWrite()
    {
        $cf = new ConcurrentFile('ConcurrentFileTest.txt');

        $data = 'hello';

        for($i = 0; $i<100; $i++)
            $cf->write($data);

        $readData = $cf->read();
        $this->assertEquals($data, $readData);
    }

    public function testPerformMultipleWriteComplexObject()
    {
        $cf = new ConcurrentFile('ConcurrentFileTest.txt');

        $data = json_decode('{
                           "name" : "Harry",
                           "age" : 23,
                           "cats" : [
                                        "fluffy", "mittens", "whiskers"
                           ]
                       }');;

        for($i = 0; $i<100; $i++)
            $cf->write($data);

        $readData = $cf->read();
        $this->assertEquals($data, $readData);
    }

}
