<?php

require_once('../iveeCoreConfig.php');

/**
 * PHPUnit test for iveeCore
 * 
 * The tests cover different parts of iveeCore and focus on the trickier cases. It is mainly used to aid during the 
 * development, but can also be used to check the correct working of an iveeCore installation.
 * 
 * To run this test, you'll need to have PHPUnit isntalled as well as created the iveeCoreConfig.php file based on the
 * provided template.
 *
 * @author aineko-m <Aineko Macx @ EVE Online>
 * @license https://github.com/aineko-m/iveeCore/blob/master/LICENSE
 * @link https://github.com/aineko-m/iveeCore/blob/master/test/IveeCoreTest.php
 * @package iveeCore
 */
class IveeCoreTest extends PHPUnit_Framework_TestCase {
    
    protected $sde;
    
    protected function setUp(){
        $this->sde = SDE::instance();
    }
    
    public function testSdeSingleton(){
        $this->assertTrue($this->sde instanceof SDE);
        $this->assertTrue($this->sde === SDE::instance());
    }
    
    public function testGetTypeAndCache(){
        //empty cache entry for type
        $this->sde->invalidateCache('type_' . 645);
        //get type
        $type = $this->sde->getType(645);
        $this->assertTrue($type instanceof Manufacturable);
        if(iveeCoreConfig::getUseMemcached())
            $this->assertTrue($type == $this->sde->getFromCache('type_' . 645));
        $this->assertTrue($type == $this->sde->getTypeByName('Dominix'));
    }
    
    public function testManufacturing(){
        //Dominix - Test if extra materials are handled correctly when PE skill level < 5
        $mpd = $this->sde->getType(645)->getBlueprint()->manufacture(1, 10, 5, false, 4);
        $this->assertTrue($mpd->getProducedType()->getTypeID() == 645);
        $this->assertTrue($mpd->getTime() == 12000);
        $materialTarget = new MaterialMap();
        $materialTarget->addMaterial(34, 10967499);
        $materialTarget->addMaterial(35, 2743561);
        $materialTarget->addMaterial(36, 690738);
        $materialTarget->addMaterial(37, 171858);
        $materialTarget->addMaterial(38, 42804);
        $materialTarget->addMaterial(39, 9789);
        $materialTarget->addMaterial(40, 3583);
        $this->assertTrue($mpd->getMaterialMap() == $materialTarget);
        
        //Improved Cloaking Device II - Tests if materials with recycle flag are handled correctly
        $mpd = $this->sde->getTypeByName('Improved Cloaking Device II')->getBlueprint()->manufacture(1, -4, 0, false, 4);
        $materialTarget = new MaterialMap();
        $materialTarget->addMaterial(9840, 10);
        $materialTarget->addMaterial(9842, 5);
        $materialTarget->addMaterial(11370, 1);
        $materialTarget->addMaterial(11483, 0.15);
        $materialTarget->addMaterial(11541, 10);
        $materialTarget->addMaterial(11693, 10);
        $materialTarget->addMaterial(11399, 16);
        $this->assertTrue($mpd->getMaterialMap() == $materialTarget);
        
        //test recursive building and adding ManufactureProcessData objects to ProcessData objects as sub-processes
        $pd = new ProcessData();
        $pd->addSubProcessData($this->sde->getTypeByName('Archon')->getBlueprint()->manufacture(1, 2, 1, true, 5));
        $pd->addSubProcessData($this->sde->getTypeByName('Rhea')->getBlueprint()->manufacture(1, -2, 1, true, 5));
        $materialTarget = new MaterialMap();
        $materialTarget->addMaterial(34, 173107652);
        $materialTarget->addMaterial(35, 28768725);
        $materialTarget->addMaterial(36, 10581008);
        $materialTarget->addMaterial(37, 1620852);
        $materialTarget->addMaterial(38, 461986);
        $materialTarget->addMaterial(39, 79255);
        $materialTarget->addMaterial(40, 31920);
        $materialTarget->addMaterial(3828, 1950);
        $materialTarget->addMaterial(11399, 3250);
        $materialTarget->addMaterial(16671, 9362621);
        $materialTarget->addMaterial(16681, 33210);
        $materialTarget->addMaterial(16682, 11520);
        $materialTarget->addMaterial(17317, 13460);
        $materialTarget->addMaterial(16680, 62220);
        $materialTarget->addMaterial(16683, 11330);
        $materialTarget->addMaterial(33362, 36600);
        $materialTarget->addMaterial(16679, 915915);
        $materialTarget->addMaterial(16678, 2444601);
        $this->assertTrue($pd->getTotalMaterialMap() == $materialTarget);
        //check skill handling
        $skillTarget = new SkillMap();
        $skillTarget->addSkill(22242, 4);
        $skillTarget->addSkill(3380, 5);
        $skillTarget->addSkill(11452, 4);
        $skillTarget->addSkill(11454, 4);
        $skillTarget->addSkill(11453, 4);
        $skillTarget->addSkill(11446, 4);
        $skillTarget->addSkill(11448, 4);
        $skillTarget->addSkill(11443, 4);
        $skillTarget->addSkill(11529, 4);
        $this->assertTrue($pd->getTotalSkillMap() == $skillTarget);
    }
    
    public function testReprocessing(){      
        $rmap = $this->sde->getTypeByName('Arkonor')->getReprocessingMaterialMap(200, 0.8825, 1);
        $materialTarget = new MaterialMap();
        $materialTarget->addMaterial(34, 8825);
        $materialTarget->addMaterial(39, 146);
        $materialTarget->addMaterial(40, 294);
        $this->assertTrue($rmap == $materialTarget);
        
        $rmap = $this->sde->getTypeByName('Zealot')->getReprocessingMaterialMap(1, 0.8825, 0.95);
        $materialTarget = new MaterialMap();
        $materialTarget->addMaterial(34, 41704);
        $materialTarget->addMaterial(35, 21779);
        $materialTarget->addMaterial(36, 7503);
        $materialTarget->addMaterial(37, 3752);
        $materialTarget->addMaterial(39, 251);
        $materialTarget->addMaterial(40, 47);
        $materialTarget->addMaterial(3828, 84);
        $materialTarget->addMaterial(11399, 84);
        $materialTarget->addMaterial(11532, 42);
        $materialTarget->addMaterial(11537, 222);
        $materialTarget->addMaterial(11539, 754);
        $materialTarget->addMaterial(11543, 3144);
        $materialTarget->addMaterial(11549, 25);
        $materialTarget->addMaterial(11554, 252);
        $materialTarget->addMaterial(11557, 252);
        $this->assertTrue($rmap == $materialTarget);
        
        $rmap = $this->sde->getTypeByName('Ark')->getReprocessingMaterialMap(1, 0.8825, 0.95);
        $materialTarget = new MaterialMap();
        $materialTarget->addMaterial(3828, 1258);
        $materialTarget->addMaterial(11399, 2096);
        $materialTarget->addMaterial(21025, 17);
        $materialTarget->addMaterial(29039, 434);
        $materialTarget->addMaterial(29053, 353);
        $materialTarget->addMaterial(29067, 376);
        $materialTarget->addMaterial(29073, 590);
        $materialTarget->addMaterial(29095, 371);
        $materialTarget->addMaterial(29103, 590);
        $materialTarget->addMaterial(29109, 849);
        $this->assertTrue($rmap == $materialTarget);
    }
    
    public function testCopying(){
        //test copying of BPs that consume materials
        $cpd = $this->sde->getTypeByName('Prototype Cloaking Device I')->getBlueprint()->copy(3, 'max', true);
        $materialTarget = new MaterialMap();
        $materialTarget->addMaterial(3812, 6000);
        $materialTarget->addMaterial(36, 24000);
        $materialTarget->addMaterial(37, 45000);
        $materialTarget->addMaterial(38, 21600);
        $this->assertTrue($cpd->getTotalMaterialMap() == $materialTarget);
        $this->assertTrue($cpd->getTotalTime() == 2830800);
    }
    
    public function testInventing(){
        $ipd = $this->sde->getTypeByName('Ishtar Blueprint')->invent(23185);
        $this->assertTrue($ipd->getInventionChance() == 0.312);
        $materialTarget = new MaterialMap();
        $materialTarget->addMaterial(23185, 1);
        $materialTarget->addMaterial(20410, 8);
        $materialTarget->addMaterial(20424, 8);
        $materialTarget->addMaterial(25855, 0);
        $this->assertTrue($ipd->getTotalMaterialMap() == $materialTarget);
    }
    
    public function testCopyInventManufacture(){
        $cimpd = $this->sde->getTypeByName('Ishtar Blueprint')->copyInventManufacture(23185);
        $materialTarget = new MaterialMap();
        $materialTarget->addMaterial(38, 9320.4);
        $materialTarget->addMaterial(3828, 420);
        $materialTarget->addMaterial(11399, 420);
        $materialTarget->addMaterial(16670, 767760);
        $materialTarget->addMaterial(16680, 19530);
        $materialTarget->addMaterial(16683, 1470);
        $materialTarget->addMaterial(16681, 9933);
        $materialTarget->addMaterial(16682, 2226);
        $materialTarget->addMaterial(33359, 10080);
        $materialTarget->addMaterial(16678, 167580);
        $materialTarget->addMaterial(17317, 210);
        $materialTarget->addMaterial(16679, 12600);
        $materialTarget->addMaterial(34, 1697862);
        $materialTarget->addMaterial(35, 373872);
        $materialTarget->addMaterial(36, 117906);
        $materialTarget->addMaterial(37, 29842.8);
        $materialTarget->addMaterial(39, 1770);
        $materialTarget->addMaterial(40, 480);
        $materialTarget->addMaterial(23185, 3.2051282051282);
        $materialTarget->addMaterial(20410, 25.641025641026);
        $materialTarget->addMaterial(20424, 25.641025641026);
        $materialTarget->addMaterial(25855, 0);
        
        //use array_diff to compare, as otherwise the floats never match
        $this->assertTrue(
            array_diff(
                $cimpd->getTotalMaterialMap()->getMaterials(), 
                $materialTarget->getMaterials()
            ) == array()
        );
    }
    
    public function testReaction(){
        $reactionProduct = $this->sde->getTypeByName('Platinum Technite');
        $this->assertTrue($reactionProduct instanceof ReactionProduct);
        //test correct handling of reaction products that can result from alchemy + refining
        $this->assertTrue($reactionProduct->getReactionIDs() == array(17952, 32831));

        //test handling of alchemy reactions with refining + feedback
        $rpd = $this->sde->getTypeByName('Unrefined Platinum Technite Reaction')->react(24 * 30, true, true, 1, 1);
        $inTarget = new MaterialMap();
        $inTarget->addMaterial(16640, 72000);
        $inTarget->addMaterial(16644, 7200);
        $this->assertTrue($rpd->getInputMaterialMap()->getMaterials() == $inTarget->getMaterials());
        $outTarget = new MaterialMap();
        $outTarget->addMaterial(16662, 14400);
        $this->assertTrue($rpd->getOutputMaterialMap()->getMaterials() == $outTarget->getMaterials());
    }
}
?>