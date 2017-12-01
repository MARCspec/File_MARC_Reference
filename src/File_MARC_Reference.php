<?php

class File_MARC_Reference
{
    /**
     * @var File_MARC_Record The MARC record
     */
    protected $record;

    /**
     * @var string|MARCspecInterface The MARCspec
     */
    protected $spec;

    /**
     * @var File_MARC_Field The current field
     */
    private $field;

    /**
     *  @var string The base field spec as string
     */
    private $baseSpec;

    /**
     *  @var MARCspecInterface The current marc spec
     */
    private $currentSpec;

    /**
     *  @var SubfieldInterface The current subfield spec
     */
    private $currentSubfieldSpec;

    /**
     *  @var SubSpecInterface The current subspec
     */
    private $currentSubSpec;

    /**
     * @var array[File_MARC_Field] Array of fields
     */
    private $fields = [];

    /**
     * @var File_MARC_Reference_Cache Instance of cached data
     */
    protected $cache;

    /**
     * @var array[FieldInterface|SubfieldInterface] Array of referenced data
     */
    protected $data = [];

    /**
     * @var array[string] Array of referenced data content
     */
    protected $content = [];

    /**
     * Constructor.
     *
     * @param string|MARCspecInterface $spec   The MARCspec
     * @param File_MARC_Record         $record The MARC record
     * @param array                    $cache  Associative array of chache data
     */
    public function __construct($spec, File_MARC_Record $record, $cache = null)
    {
        $this->record = $record;

        if ($cache instanceof File_MARC_Reference_Cache) {
            $this->cache = $cache;
        } else {
            $this->cache = new File_MARC_Reference_Cache();
        }

        $this->spec = $this->cache->spec($spec);

        $this->interpreteSpec();
    }

    /**
     * Interpretes the MARCspec to decide what methods to call.
     */
    private function interpreteSpec()
    {
        $this->baseSpec = $this->spec['field']->getBaseSpec();
        $this->referenceFields();
        if (!$this->fields) {
            return;
        }
        $fieldIndex = $this->spec['field']->getIndexStart();
        $prevTag = '';
        $this->currentSpec = clone $this->spec;
        foreach ($this->fields as $this->field) {
            if ($this->field instanceof File_MARC_Field) { // not for leader
                // adjust spec to current field repetition
                $tag = $this->field->getTag();
                $fieldIndex = $this->getFieldIndex($prevTag, $tag, $fieldIndex);
                $this->currentSpec['field']->setIndexStartEnd($fieldIndex, $fieldIndex);
                $this->baseSpec = $this->currentSpec['field']->getBaseSpec();
            } else {
                $tag = 'LDR';
            }
            /*
             *  Subfield iteration
             */
            if ($this->spec->offsetExists('subfields')) {
                if ($this->field instanceof File_MARC_Data_Field) {
                    foreach ($this->spec['subfields'] as $currentSubfieldSpec) {
                        if ($_subfields = $this->referenceSubfields($currentSubfieldSpec)) {
                            foreach ($_subfields as $subfieldIndex => $subfield) {
                                $currentSubfieldSpec->setIndexStartEnd($subfieldIndex, $subfieldIndex);
                                /*
                                *  Subfield SubSpec validation
                                */
                                if ($currentSubfieldSpec->offsetExists('subSpecs')) {
                                    $valid = $this->iterateSubSpec(
                                        $currentSubfieldSpec['subSpecs'],
                                        $fieldIndex,
                                        $subfieldIndex
                                    );

                                    if ($valid) {
                                        $this->ref($currentSubfieldSpec, $subfield);
                                    }
                                } else {
                                    $this->ref($currentSubfieldSpec, $subfield);
                                }
                            }
                        }
                    } // end foreach subfield spec
                }
            } elseif ($this->spec->offsetExists('indicator')) {
                if ($this->field instanceof File_MARC_Data_Field) {
                    /*
                    *  Field SubSpec validation
                    */
                    if ($this->currentSpec['indicator']->offsetExists('subSpecs')) {
                        $valid = $this->iterateSubSpec($this->currentSpec['indicator']['subSpecs'], $fieldIndex);
                        if (!$valid) {
                            $fieldIndex++;
                            $prevTag = $tag;
                            continue; // field subspec must be valid
                        }
                    }
                    $position = (int) $this->currentSpec['indicator']['position'];
                    $this->ref($this->currentSpec['indicator'], $this->field->getIndicator($position));
                }
            } else {
                /*
                *  Field SubSpec validation
                */
                if ($this->currentSpec['field']->offsetExists('subSpecs')) {
                    $valid = $this->iterateSubSpec($this->currentSpec['field']['subSpecs'], $fieldIndex);
                    if (!$valid) {
                        $fieldIndex++;
                        $prevTag = $tag;
                        continue; // field subspec must be valid
                    }
                }

                $this->ref($this->currentSpec['field'], $this->field);
            }
            $fieldIndex++;
            $prevTag = $tag;
        } // end foreach fields
    }

    /**
     * Get the current field index.
     *
     * @param string $prevTag    The previous field tag
     * @param string $tag        The current field tag
     * @param int    $fieldIndex The current field index
     *
     * @return int $fieldIndex The current field index
     */
    private function getFieldIndex($prevTag, $tag, $fieldIndex)
    {
        if ($prevTag == $tag or '' == $prevTag) {
            return $fieldIndex; // iteration of field index will continue
        }
        $specTag = $this->currentSpec['field']->getTag();
        if (preg_match('/'.$specTag.'/', $tag)) {
            // not same field tag, but field spec tag matches
            return $fieldIndex; // iteration of field index will continue
        }
        // not same field tag, iteration gets reset
        return $this->spec['field']->getIndexStart();
    }

    /**
     * Iterate on subspecs.
     *
     * @param array $subSpecs      Array of subspecs
     * @param int   $fieldIndex    The current field index
     * @param int   $subfieldIndex The current subfield index
     *
     * @return bool The validation result
     */
    private function iterateSubSpec($subSpecs, $fieldIndex, $subfieldIndex = null)
    {
        $valid = true;
        foreach ($subSpecs as $_subSpec) {
            if (is_array($_subSpec)) { // chained subSpecs (OR)
                foreach ($_subSpec as $this->currentSubSpec) {
                    $this->setIndexStartEnd($fieldIndex, $subfieldIndex);
                    if ($valid = $this->checkSubSpec()) {
                        break; // at least one of them is true (OR)
                    }
                }
            } else {
                // repeated SubSpecs (AND)
                $this->currentSubSpec = $_subSpec;
                $this->setIndexStartEnd($fieldIndex, $subfieldIndex);
                if (!$valid = $this->checkSubSpec()) {
                    break; // all of them have to be true (AND)
                }
            }
        }

        return $valid;
    }

    /**
     * Sets the start and end index of the current spec
     * if it's an instance of CK\MARCspec\PositionOrRangeInterface.
     *
     * @param object $spec          The current spec
     * @param int    $fieldIndex    the start/end index to set
     * @param int    $subfieldIndex the start/end index to set
     */
    private function setIndexStartEnd($fieldIndex, $subfieldIndex = null)
    {
        foreach (['leftSubTerm', 'rightSubTerm'] as $side) {
            if (!($this->currentSubSpec[$side] instanceof CK\MARCspec\ComparisonStringInterface)) {
                // only set new index if subspec field tag equals spec field tag!!
                if ($this->spec['field']['tag'] == $this->currentSubSpec[$side]['field']['tag']) {
                    $this->currentSubSpec[$side]['field']->setIndexStartEnd($fieldIndex, $fieldIndex);
                    if (!is_null($subfieldIndex)) {
                        if ($this->currentSubSpec[$side]->offsetExists('subfields')) {
                            foreach ($this->currentSubSpec[$side]['subfields'] as $subfieldSpec) {
                                $subfieldSpec->setIndexStartEnd($subfieldIndex, $subfieldIndex);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Checks cache for subspec validation result.
     * Validates SubSpec if it's not in cache.
     *
     * @return bool Validation result
     */
    private function checkSubSpec()
    {
        $validation = $this->cache->validation($this->currentSubSpec);
        if (!is_null($validation)) {
            return $validation;
        }

        return $this->validateSubSpec();
    }

    /**
     * Validates a subSpec.
     *
     * @return bool True if subSpec is valid and false if not
     */
    private function validateSubSpec()
    {
        if ('!' != $this->currentSubSpec['operator'] && '?' != $this->currentSubSpec['operator']) { // skip left subTerm on operators ! and ?
            if (false === ($this->currentSubSpec['leftSubTerm'] instanceof CK\MARCspec\ComparisonStringInterface)) {
                $leftSubTermReference = new self(
                    $this->currentSubSpec['leftSubTerm'],
                    $this->record,
                    $this->cache
                );
                if (!$leftSubTerm = $leftSubTermReference->content) { // see 2.3.4 SubSpec validation
                    return $this->cache->validation($this->currentSubSpec, false);
                }
            } else {
                // is a CK\MARCspec\ComparisonStringInterface
                $leftSubTerm[] = $this->currentSubSpec['leftSubTerm']['comparable'];
            }
        }

        if (false === ($this->currentSubSpec['rightSubTerm'] instanceof CK\MARCspec\ComparisonStringInterface)) {
            $rightSubTermReference = new self(
                $this->currentSubSpec['rightSubTerm'],
                $this->record,
                $this->cache
            );
            $rightSubTerm = $rightSubTermReference->content; // content maybe an empty array
        } else {
            // is a CK\MARCspec\ComparisonStringInterface
            $rightSubTerm[] = $this->currentSubSpec['rightSubTerm']['comparable'];
        }
        $validation = false;

        switch ($this->currentSubSpec['operator']) {
        case '=':
            if (0 < count(array_intersect($leftSubTerm, $rightSubTerm))) {
                $validation = true;
            }
            break;

        case '!=':
            if (0 < count(array_diff($leftSubTerm, $rightSubTerm))) {
                $validation = true;
            }
            break;

        case '~':
            if (0 < count(
                array_uintersect(
                    $leftSubTerm,
                    $rightSubTerm,
                    function ($v1, $v2) {
                        if (strpos($v1, $v2) !== false) {
                            return 0;
                        }

                        return -1;
                    }
                )
            )
            ) {
                $validation = true;
            }
            break;

        case '!~':
            if (0 < count(
                array_uintersect(
                    $leftSubTerm,
                    $rightSubTerm,
                    function ($v1, $v2) {
                        if (strpos($v1, $v2) === false) {
                            return 0;
                        }

                        return -1;
                    }
                )
            )
            ) {
                $validation = true;
            }
            break;

        case '?':
            if ($rightSubTerm) {
                $validation = true;
            }
            break;

        case '!':
            if (!$rightSubTerm) {
                $validation = true;
            }
            break;
        }

        $this->cache->validation($this->currentSubSpec, $validation);

        return $validation;
    }

    /**
     * Reference fields by field tag.
     *
     * @return array Array of referred fields
     */
    private function referenceFieldsByTag()
    {
        $tag = $this->spec['field']['tag'];

        if ($this->cache->offsetExists($tag)) {
            return $this->cache[$tag];
        }

        if ('LDR' !== $tag) {
            $_fieldRef = $this->record->getFields($tag, true);
        } else {
            // tag = LDR
            $_fieldRef[] = $this->record->getLeader();
        }

        $this->cache[$tag] = $_fieldRef;

        return $_fieldRef;
    }

    /**
     * Reference fields. Filter by index and indicator.
     *
     * @return array Array of referenced fields
     */
    private function referenceFields()
    {
        if ($this->fields = $this->cache->getData($this->spec['field'])) {
            return $this->fields;
        }

        if (!$this->fields = $this->referenceFieldsByTag()) {
            return;
        }

        /*
        * filter by indizes
        */
        if ($_indexRange = $this->getIndexRange($this->spec['field'], count($this->fields))) {
            $prevTag = '';
            $index = 0;
            foreach ($this->fields as $position => $field) {
                if (false == ($field instanceof File_MARC_Field)) {
                    continue;
                }
                $tag = $field->getTag();
                $index = ($prevTag == $tag or '' == $prevTag) ? $index : 0;
                if (!in_array($index, $_indexRange)) {
                    unset($this->fields[$position]);
                }
                $index++;
                $prevTag = $tag;
            }
        }

        /*
        * filter for indicator values
        */
        if ($this->spec->offsetExists('indicator')) {
            foreach ($this->fields as $key => $field) {
                // only filter by indicators for data fields
                if (!$field->isDataField()) {
                    // control field have no indicators
                    unset($this->fields[$key]);
                }
            }
        }
    }

    /**
     * Reference subfield contents and filter by index.
     *
     * @param SubfieldInterface $currentSubfieldSpec The current subfield spec
     *
     * @return array An array of referenced subfields
     */
    private function referenceSubfields($currentSubfieldSpec)
    {
        $baseSubfieldSpec = $this->baseSpec.$currentSubfieldSpec->getBaseSpec();

        if ($subfields = $this->cache->getData($this->currentSpec['field'], $currentSubfieldSpec)) {
            return $subfields;
        }

        $_subfields = $this->field->getSubfields($currentSubfieldSpec['tag']);

        if (!$_subfields) {
            $this->cache[$baseSubfieldSpec] = [];

            return [];
        }

        /* filter on indizes */
        if ($_indexRange = $this->getIndexRange($currentSubfieldSpec, count($_subfields))) {
            foreach ($_subfields as $sfkey => $item) {
                if (!in_array($sfkey, $_indexRange) || $item->isEmpty()) {
                    unset($_subfields[$sfkey]);
                }
            }
        }

        if ($_subfields) {
            $sf_values = array_values($_subfields);
            $this->cache[$baseSubfieldSpec] = $sf_values;

            return $sf_values;
        }

        $this->cache[$baseSubfieldSpec] = [];

        return [];
    }

    /**
     * Calculates a range from indexStart and indexEnd.
     *
     * @param array $spec  The spec with possible indizes
     * @param int   $total Total count of (sub)fields
     *
     * @return array The range of indizes
     */
    private function getIndexRange($spec, $total)
    {
        $lastIndex = $total - 1;
        $indexStart = $spec['indexStart'];
        $indexEnd = $spec['indexEnd'];
        if ('#' === $indexStart) {
            if ('#' === $indexEnd or 0 === $indexEnd) {
                return [$lastIndex];
            }
            $indexStart = $lastIndex;
            $indexEnd = $lastIndex - $indexEnd;
            $indexEnd = (0 > $indexEnd) ? 0 : $indexEnd;
        } else {
            if ($lastIndex < $indexStart) {
                return [$indexStart]; // this will result to no hits
            }

            $indexEnd = ('#' === $indexEnd) ? $lastIndex : $indexEnd;
            if ($indexEnd > $lastIndex) {
                $indexEnd = $lastIndex;
            }
        }

        return range($indexStart, $indexEnd);
    }

    /**
     * Reference data and set content.
     *
     * @param FieldInterface|SubfieldInterface          $spec  The corresponding spec
     * @param string|File_MARC_Field|File_MARC_Subfield $value The value to reference
     */
    public function ref($spec, $value)
    {
        array_push($this->data, $value);
        /*
        * set content
        */
        $value = $this->cache->getContents($spec, [$value]);
        $this->content = array_merge($this->content, $value);
    }

    /**
     * Get protected attributes.
     */
    public function __get($name)
    {
        switch ($name) {
        case 'data':
            return $this->data;
            break;
        case 'content':
            return $this->content;
            break;
        case 'cache':
            return $this->cache;
            break;
        }
    }
}
