<?php

// {{{ICINGA_LICENSE_HEADER}}}
/**
 * This file is part of Icinga 2 Web.
 *
 * Icinga 2 Web - Head for multiple monitoring backends.
 * Copyright (C) 2013 Icinga Development Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @copyright 2013 Icinga Development Team <info@icinga.org>
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL, version 2
 * @author    Icinga Development Team <info@icinga.org>
 */
// {{{ICINGA_LICENSE_HEADER}}}


namespace Tests\Icinga\PreservingIniWriterTest;

require_once 'Zend/Config.php';
require_once 'Zend/Config/Ini.php';
require_once 'Zend/Config/Writer/Ini.php';
require_once('../../library/Icinga/Config/IniEditor.php');
require_once('../../library/Icinga/Config/PreservingIniWriter.php');

use Icinga\Config\PreservingIniWriter;

class PreservingIniWriterTest extends \PHPUnit_Framework_TestCase {

    private $tmpfiles = array();

    /**
     * Set up the test fixture
     */
    public function setUp()
    {
        $ini =
';1
trailing1="wert"
arr[]="0"
arr[]="1"
arr[]="2"
arr[]="3"

;2
;3
Trailing2=
[parent]
;4
;5
;6
;7
list[]="zero"
list[]="one"

;8
;9
many.many.nests="value"
propOne="value1"
propTwo="2"
propThree=
propFour="true"

Prop5="true"

[child : parent]
PropOne="overwritten"
;10      
';
        $this->writeToTmp('orig',$ini);

        $this->writeToTmp('sonst','');

        $emptyIni = " ";
        $this->writeToTmp('empty',$emptyIni);

        $editedIni =
';1
;2
;3
;4
;5
trailing1="1"

[parent]
;6
;7
;8
;9
;10
propOne="value1"

[different]
prop1="1"
prop2="2"

[nested : different]
prop2="5"
';
        $this->writeToTmp('edited',$editedIni);
    }

    /**
     * Write a string to a temporary file
     *
     * @param $name The name of the temporary file
     * @param $content The content
     */
    private function writeToTmp($name,$content)
    {
        $this->tmpfiles[$name] =
            tempnam(dirname(__FILE__) . '/temp',$name);
        $file = fopen($this->tmpfiles[$name],'w');
        fwrite($file,$content);
        fflush($file);
        fclose($file);
    }

    /**
     * Tear down the test fixture
     */
    public function tearDown()
    {
        foreach ($this->tmpfiles as $filename) {
            unlink($filename);
        }
    }

    /**
     * Test if the IniWriter works correctly when writing the changes back to
     * the same ini file
     */
    public function testPropertyChangeSameConfig()
    {
        $this->changeConfigAndWriteToFile('orig');
        $config = new \Zend_Config_Ini(
            $this->tmpfiles['orig'],null,array('allowModifications' => true)
        );
        $this->checkConfigProperties($config);
        $this->checkConfigComments($this->tmpfiles['orig']);
    }

    /**
     * Test if the IniWriter works correctly when writing to an empty file
     */
    public function testPropertyChangeEmptyConfig()
    {
        $this->changeConfigAndWriteToFile('empty');
        $config = new \Zend_Config_Ini(
            $this->tmpfiles['empty'],null,array('allowModifications' => true)
        );
        $this->checkConfigProperties($config);
    }

    /**
     * Test if the IniWriter works correctly when writing to a file with changes
     */
    public function testPropertyChangeEditedConfig()
    {
        $original = $this->changeConfigAndWriteToFile('edited');
        $config = new \Zend_Config_Ini(
            $this->tmpfiles['edited'],null,array('allowModifications' => true)
        );
        $this->checkConfigProperties($config);
        $this->checkConfigComments($this->tmpfiles['edited']);
    }

    /**
     * Change the test config and write the changes to the temporary
     * file $tmpFile
     *
     * @param $tmpFile
     */
    private function changeConfigAndWriteToFile($tmpFile)
    {
        $config = $this->createTestConfig();
        $this->alterConfig($config);
        $writer = new PreservingIniWriter(
            array('config' => $config,'filename' => $this->tmpfiles[$tmpFile])
        );
        $writer->write();
        return $config;
    }

    /**
     * Check if all comments are present
     *
     * @param $file
     */
    private function checkConfigComments($file)
    {
        $i = 0;
        foreach (explode("\n",file_get_contents($file)) as $line) {
            if (preg_match('/^;/',$line)) {
                $i++;
                $this->assertEquals(
                    $i,intval(substr($line,1)),
                    'Comment unchanged'
                );
            }
        }
        $this->assertEquals(10,$i,'All comments exist');
    }

    /**
     * Test if all configuration properties are set correctly
     *
     * @param $config
     */
    private function checkConfigProperties($config)
    {
        $this->assertEquals('val',$config->Trailing2,
            'Section-less property updated.');

        $this->assertNull($config->trailing1,
            'Section-less property deleted.');

        $this->assertEquals('value',$config->new,
            'Section-less property created.');

        $this->assertEquals('0',$config->arr->{0},
            'Value persisted in array');

        $this->assertEquals('update',$config->arr->{2},
            'Value changed in array');

        $this->assertEquals('arrvalue',$config->arr->{4},
            'Value added to array');

        $this->assertEquals('',$config->parent->propOne,
            'Section property deleted.');

        $this->assertEquals("2",$config->parent->propTwo,
            'Section property numerical unchanged.');

        $this->assertEquals('update',$config->parent->propThree,
            'Section property updated.');

        $this->assertEquals("true",$config->parent->propFour,
            'Section property boolean unchanged.');

        $this->assertEquals("1",$config->parent->new,
            'Section property numerical created.');

        $this->assertNull($config->parent->list->{0},
            'Section array deleted');

        $this->assertEquals('new',$config->parent->list->{1},
            'Section array changed.');

        $this->assertEquals('changed',$config->parent->many->many->nests,
            'Change strongy nested value.');

        $this->assertEquals('new',$config->parent->many->many->new,
            'Ccreate strongy nested value.');

        $this->assertEquals('overwritten',$config->child->PropOne,
            'Overridden inherited property unchanged.');

        $this->assertEquals('somethingNew',$config->child->propTwo,
            'Inherited property changed.');

        $this->assertEquals('test',$config->child->create,
            'Non-inherited property created.');

        $this->assertInstanceOf('Zend_Config',$config->newsection,
            'New section created.');

        $extends = $config->getExtends();
        $this->assertEquals('child',$extends['newsection'],
            'New inheritance created.');
    }

    /**
     * Change the content of a Zend_Config
     *
     * @param Zend_Config $config
     */
    private function alterConfig(\Zend_Config $config)
    {
        $config->Trailing2 = 'val';
        unset($config->trailing1);
        $config->new = 'value';
        $config->arr->{2} = "update";
        $config->arr->{4} = "arrvalue";

        $config->parent->propOne = null;
        $config->parent->propThree = 'update';
        $config->parent->new = 1;
        unset($config->parent->list->{0});
        $config->parent->list->{1} = 'new';

        $config->parent->many->many->nests = "changed";
        $config->parent->many->many->new = "new";

        $config->child->propTwo = 'somethingNew';
        $config->child->create = 'test';

        $config->newsection = array();
        $config->newsection->p1 = "prop";
        $config->newsection->P2 = "prop";
        $config->setExtend('newsection','child');
    }

    /**
     * Create the the configuration that will be used for the tests.
     */
    private function createTestConfig()
    {
        return new \Zend_Config_Ini(
            $this->tmpfiles['orig'],
            null,
            array('allowModifications' => true)
        );
    }
}
