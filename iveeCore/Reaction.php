<?php
/**
 * Reaction class file.
 *
 * PHP version 5.4
 *
 * @category IveeCore
 * @package  IveeCoreClasses
 * @author   Aineko Macx <ai@sknop.net>
 * @license  https://github.com/aineko-m/iveeCore/blob/master/LICENSE GNU Lesser General Public License
 * @link     https://github.com/aineko-m/iveeCore/blob/master/iveeCore/Reaction.php
 */

namespace iveeCore;

/**
 * Class for all Reactions
 * Inheritance: Reaction -> Type -> SdeType -> CoreDataCommon
 *
 * @category IveeCore
 * @package  IveeCoreClasses
 * @author   Aineko Macx <ai@sknop.net>
 * @license  https://github.com/aineko-m/iveeCore/blob/master/LICENSE GNU Lesser General Public License
 * @link     https://github.com/aineko-m/iveeCore/blob/master/iveeCore/Reaction.php
 */
class Reaction extends Type
{
    /**
     * @var \iveeCore\MaterialMap $cycleInputMaterialMap contains the consumed materials for one reaction cycle
     */
    protected $cycleInputMaterialMap;

    /**
     * @var \iveeCore\MaterialMap $cycleOutputMaterialMap contains the output materials for one reaction cycle
     */
    protected $cycleOutputMaterialMap;

    /**
     * @var bool $isAlchemy defines if this reaction is an alchemy reaction
     */
    protected $isAlchemy = false;

    /**
     * Constructor. Use iveeCore\Type::getById() to instantiate Reaction objects.
     *
     * @param int $id of the Reaction object
     *
     * @throws Exception if typeId is not found
     */
    protected function __construct($id)
    {
        //call parent constructor
        parent::__construct($id);

        //get data from SQL
        $row = $this->queryAttributes();
        //set data to object attributes
        $this->setAttributes($row);

        $materialMapClass = Config::getIveeClassName('MaterialMap');
        $this->cycleInputMaterialMap = new $materialMapClass;
        $this->cycleOutputMaterialMap = new $materialMapClass;

        $sdeClass  = Config::getIveeClassName('SDE');

        //get reaction materials
        $res = $sdeClass::instance()->query(
            'SELECT itr.input,
            itr.typeID,
            itr.quantity * IFNULL(COALESCE(dta.valueInt, dta.valueFloat), 1) as quantity
            FROM invTypeReactions as itr
            JOIN invTypes as it ON itr.typeID = it.typeID
            LEFT JOIN dgmTypeAttributes as dta ON itr.typeID = dta.typeID
            WHERE it.published = 1
            AND (dta.attributeID = 726 OR dta.attributeID IS NULL)
            AND itr.reactionTypeID = ' . $this->id . ';'
        );

        while ($row = $res->fetch_assoc()) {
            if ($row['input'] == 1) {
                $this->cycleInputMaterialMap->addMaterial($row['typeID'], $row['quantity']);
            } else {
                $this->cycleOutputMaterialMap->addMaterial($row['typeID'], $row['quantity']);
                if (Type::getById($row['typeID'])->isReprocessable()) {
                    $this->isAlchemy = true;
                }
            }
        }
    }

    /**
     * Gets the MaterialMap of input materials for one reaction cycle as clone.
     *
     * @return \iveeCore\MaterialMap
     */
    public function getCycleInputMaterialMap()
    {
        return clone $this->cycleInputMaterialMap;
    }

    /**
     * Gets the MaterialMap of output materials for one reaction cycle as clone.
     *
     * @return \iveeCore\MaterialMap
     */
    public function getCycleOutputMaterialMap()
    {
        return clone $this->cycleOutputMaterialMap;
    }

    /**
     * Returns whether this reaction is an alchemy reaction or not.
     *
     * @return bool
     */
    public function isAlchemy()
    {
        return $this->isAlchemy;
    }

    /**
     * Produces an ReactionProcessData object detailing a reaction process for a given number of reaction cycles.
     *
     * @param \iveeCore\IndustryModifier $iMod as industry context
     * @param int|float $cycles defines the number of reaction cycles to be calculated. One cycle takes 1h to complete.
     * @param bool $reprocess defines reprocessable reaction outputs should be reprocessed in the process. Applies to
     * alchemy reaction.
     * @param bool $feedback defines if materials occuring in both input and output should be subtracted in the
     * possible numbers, thus showing the effective input/output materials. Applies to alchemy reactions.
     * @param int $recursionDepth defines the maximum number of reaction recursions
     *
     * @return \iveeCore\ReactionProcessData
     */
    public function react(IndustryModifier $iMod, $cycles = 1, $reprocess = true, $feedback = true, $recursionDepth = 0)
    {
        $reactionProcessDataClass = Config::getIveeClassName('ReactionProcessData');
        $materialMapClass = Config::getIveeClassName('MaterialMap');

        //get material input and output maps for one cycle
        $imm = $this->getCycleInputMaterialMap();
        $omm = $this->getCycleOutputMaterialMap();

        //if refine flag set, replace the refinable output materials by their refined materials
        if ($reprocess) {
            $omm->reprocessMaterials($iMod);
        }

        //if feedback flag set, subtract materials occurring in both input and output from each other, respecting
        //quantities. This gives the effective required and resulting materials.
        if ($feedback) {
            $materialMapClass::symmetricDifference($imm, $omm);
        }

        //multiply amounts by cycles as factor
        $imm->multiply($cycles);
        $omm->multiply($cycles);

        $rpd = new $reactionProcessDataClass(
            $this->id,
            $imm,
            $omm,
            $iMod->getSolarSystem()->getId(),
            $cycles,
            ($this->isAlchemy and $reprocess), //only pass on refine flag if this reaction actually produces a refinable
            ($this->isAlchemy and $feedback) //only pass feedback flag it it actually was used in reaction
        );

        //if we are doing reaction recursion, replace ReactionProducts in input by their equivalent ReactionProcessData
        if ($recursionDepth > 0) {
            foreach ($imm->getMaterials() as $typeId => $quantity) {
                $mat = Type::getById($typeId);
                if ($mat instanceof ReactionProduct) {
                    $rpd->addSubProcessData($mat->doBestReaction($iMod, $quantity, $recursionDepth - 1));
                    $imm->subtractMaterial($typeId, $quantity);
                }
            }
        }

        return $rpd;
    }

    /**
     * Produces an ReactionProcessData object detailing a reaction process for an exact number of desired output
     * materials. This will likely result in fractionary number of reaction cycles. It also implies output reprocessing
     * and reaction feedback (if applicable).
     *
     * @param \iveeCore\IndustryModifier $iMod as industry context
     * @param int|float $units defines the number of desired output material units
     * @param int $recursionDepth defines the maximum number of reaction recursions
     *
     * @return \iveeCore\ReactionProcessData
     */
    public function reactExact(IndustryModifier $iMod, $units, $recursionDepth)
    {
        //determine the output material quantity from a single reaction cycle
        $singleCycleOutput = $this->react($iMod, 1, true, true, 0)->getOutputMaterialMap()->getMaterials();
        $singleCycleQuantity = array_pop($singleCycleOutput);

        //run reaction with adjusted fractionary number of cycles
        return $this->react($iMod, $units / $singleCycleQuantity, true, true, $recursionDepth);
    }
}
