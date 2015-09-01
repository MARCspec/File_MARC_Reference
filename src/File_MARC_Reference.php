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
     * @var File_MARC_Field The current field spec
     */ 
    private $field;
    
    /**
    *  @var int The current field index
    */
    private $index;
    
    /**
    *  @var string The base fieldspec as string
    */
    private $baseSpec;
    
    /**
    *  @var string The base subfieldspec as string
    */
    private $baseSubfieldSpec;
    
    /**
     * @var string The current spec processed
     */ 
    private $currentSpec;
    
    /**
     * @var array of fields
     */
    private $fields = [];

    /**
     * @var array Associative array cache of referred data
     */ 
    public $cache;

    /**
     * @var array[File_MARC_Field|File_MARC_Subfield] Data referred
     */ 
    public $data = [];
    
    /**
     * @var array Data content referred
     */ 
    public $content = [];
    
    /**
     * Constructor
     * 
     * @param string|MARCspecInterface $spec The MARCspec
     * @param File_MARC_Record $record The MARC record
     * @param array[fieldPosition => index] Array of field positions 
     */ 
    function __construct($spec, \File_MARC_Record $record, $cache = ['spec'=>[],'data'=>[],'content'=>[],'validation'=>[]])
    {
        print "\nNEW SPEC ".$spec." with cache\n";
        var_dump($cache);
        
        $this->record = $record;
        
        $this->cache = $cache;

        if(is_string($spec))
        {
            if(array_key_exists($spec,$this->cache['spec']))
            {
                $this->spec = $this->cache['spec'][$spec];
            }
            else
            {
                $this->spec = new CK\MARCspec\MARCspec($spec);
                $this->cache['spec'][$this->spec->__toString()] = $this->spec;
            }
            
            
        }
        elseif($spec instanceOf CK\MARCspec\MARCspecInterface)
        {
            $this->spec = $spec;

            $spec = $spec->__toString();
        }
        
        if(array_key_exists($spec,$this->cache))
        {
        print "\n __construct spec $spec exists in cache\n";
         var_dump($cache);
            $this->data = $this->cache[$spec];
            $this->content = $this->cache['content'][$spec];
        }
        else
        {
            $this->interpreteSpec();
            print "\nWRITING CACHE B $spec\n";
            $this->cache[$spec] = $this->data;
            $this->cache['content'][$spec] = $this->content;
            print "\nEND of spec $spec with cache\n";
            var_dump($this->cache);
        }
    }

    /**
     * Interpretes the MARCspec to decide what methods to call
     */
    private function interpreteSpec()
    {
        $this->baseSpec = $this->spec['field']->getBaseSpec();
        $this->baseSpecOriginal = $this->baseSpec;
        $this->referenceFields();
        
        if(!$this->fields) return;
#print "\nCache after referenceFields\n";
#var_dump($this->cache);
        foreach($this->fields as $fieldIndex => $this->field)
        {
            // adjust spec to current field repetition
            $this->spec['field']->setIndexStartEnd($fieldIndex,$fieldIndex);
            
            $this->baseSpec = $this->spec['field']->getBaseSpec();
            
            // content cache is always wanted
            $this->currentSpec = $this->spec['field'];
            $contents = $this->getContents($this->field);
            $this->cache['content'] = array_merge($this->cache['content'],[$this->baseSpec => $contents]);
            
            if($this->spec['field']->offsetExists('subSpecs'))
            {
                $valid = true;
                
                foreach($this->spec['field']['subSpecs'] as $_subSpec) // AND
                {
                    if(is_array($_subSpec)) // OR
                    {
                        foreach($_subSpec as $subSpec)
                        {
                            $this->setIndexStartEnd($subSpec,$fieldIndex);
                            
                            if($valid = $this->checkSubSpec($subSpec))
                            {
                                break; // at least one of them is true (OR)
                            }
                        }
                    }
                    else
                    {
                        $this->setIndexStartEnd($_subSpec,$fieldIndex);
                        
                        if(!$valid = $this->checkSubSpec($_subSpec))
                        {
                            break; // all of them have to be true (AND)
                        }
                    }
                }
                #print "\nfor $this->spec['field'] validation:\n";
                #var_dump($valid);
                if(!$valid) continue; // field subspec must be valid
            }

            if($this->spec->offsetExists('subfields'))
            {
                if($this->field instanceOf File_MARC_Data_Field)
                {
                    foreach($this->spec['subfields'] as $this->currentSubfieldSpec)
                    {
                        $this->baseSubfieldSpec = $this->baseSpec.$this->currentSubfieldSpec->getBaseSpec();
           print "\nbefore Subfield currentSpec:".$this->baseSubfieldSpec."\n";

                        if($_subfields = $this->referenceSubfields())
                        {
                            foreach($_subfields as $subfieldIndex => $subfield)
                            {
                                $this->currentSubfieldSpec->setIndexStartEnd($subfieldIndex,$subfieldIndex);
                                
                                $this->baseSubfieldSpec = $this->baseSpec.$this->currentSubfieldSpec->getBaseSpec();
         print "\nafter Subfield currentSpec:".$this->baseSubfieldSpec." with fieldIndex $fieldIndex of total ".count($_subfields)." Subfields\n";
                                
                                $this->currentSpec = $this->currentSubfieldSpec;
                                $contents = [$this->getContents($subfield)];
                                $this->cache['content'] = array_merge($this->cache['content'],[$this->baseSubfieldSpec => $contents]);
                                
                                
                                // SubSpec Validation
                                if($this->currentSubfieldSpec->offsetExists('subSpecs'))
                                {
                                    $valid = true;
                                    
                                    foreach($this->currentSubfieldSpec['subSpecs'] as $_subSpec)
                                    {
                                        if(is_array($_subSpec)) // chained subSpecs (OR)
                                        {
                                            foreach($_subSpec as $subSpec)
                                            {
                                                $this->setIndexStartEnd($subSpec,$fieldIndex,$subfieldIndex);

                                                if($valid = $this->checkSubSpec($subSpec))
                                                {
                                                    break; // at least one of them is true (OR)
                                                }
                                            }
                                        }
                                        else // repeated SubSpecs (AND)
                                        {

                                            $this->setIndexStartEnd($_subSpec,$fieldIndex,$subfieldIndex);

                                            if(!$valid = $this->checkSubSpec($_subSpec))
                                            {
                                                break; // all of them have to be true (AND)
                                            }
                                        }
                                    }
                                    
                                    if(!$valid) continue; // subfield subSpecs must be valid
                                }
                                print "\nWRITING CACHE A $this->baseSubfieldSpec\n";
                                $this->cache[$this->baseSubfieldSpec] = $subfield;
                                
                                $this->setDataContent($subfield,$this->currentSubfieldSpec,"subfields");
                            }
                        }
                        
                    } // end foreach subfield spec
                }


            }
            else // Only a field spec
            {
                $this->cache[$this->baseSpec] = $this->field;
                $this->setDataContent($this->field,$this->spec['field'],"field");
                
            }
        } // end foreach fields
        #$this->cache[$this->baseSpecOriginal] = $this->fields;
        #print "\nafter ALL DATA: \n";
        #var_dump($this->data);
    }
    
    /**
    * Sets the start and end index of the current spec
    * if it's an instance of CK\MARCspec\PositionOrRangeInterface
    * 
    * @param object $spec The current spec 
    * @param int $index the start/end index to set
    */ 
    private function setIndexStartEnd(&$subSpec,$fieldIndex,$subfieldIndex = null)
    {
print "\nbefore setIndexStartEnd $subSpec\n";
        foreach(['leftSubTerm','rightSubTerm'] as $side)
        {
            if(!($subSpec[$side] instanceOf CK\MARCspec\ComparisonStringInterface))
            {
            print "\n".$this->spec['field']['tag']." =?= ".$subSpec[$side]['field']['tag']."\n";
                // only set new index if subspec field tag equals spec field tag
                if($this->spec['field']['tag'] == $subSpec[$side]['field']['tag']) 
                {
                    $subSpec[$side]['field']->setIndexStartEnd($fieldIndex,$fieldIndex);

                    if(!is_null($subfieldIndex))
                    {
                        if($subSpec[$side]->offsetExists('subfields'))
                        {
                            foreach($subSpec[$side]['subfields'] as $subfieldSpec)
                            {
                                $subfieldSpec->setIndexStartEnd($subfieldIndex,$subfieldIndex);
                            }
                        }
                    }
                }
            }
        }
print "\nafter setIndexStartEnd $subSpec\n";
    }
    
    /**
    * Checks cache for subspec validation result.
    * Validates SubSpec if it's not in cache
    * 
    * @param CK\MARCspec\SubSpecInterface $subSpec The subSpec to be validated
    * @return bool Validation result
    */ 
    private function checkSubSpec(CK\MARCspec\SubSpecInterface $subSpec)
    {
        $this->baseSubSpec = "{".$subSpec->__toString()."}";
print "\nvalidation subspec $this->baseSubSpec\n";
        if(array_key_exists($this->baseSubSpec,$this->cache['validation']))
        {
        print "\nvalidation subspec $this->baseSubSpec from cache with value\n";
        var_dump($this->cache['validation'][$this->baseSubSpec]);
            return $this->cache['validation'][$this->baseSubSpec];
        }
        
        return $this->validateSubSpec($subSpec);
    }

    /**
     * Validates a subSpec
     * 
     * @param CK\MARCspec\SubSpecInterface $subSpec The subSpec to be validated
     * @return bool True if subSpec is valid and false if not
     */ 
    private function validateSubSpec(CK\MARCspec\SubSpecInterface $subSpec)
    {
     print "\n in validateSubSpec $subSpec\n";
        

        if("!" != $subSpec['operator'] && "?" != $subSpec['operator']) // skip left subTerm on operators ! and ?
        {
        
            if(false === ($subSpec['leftSubTerm'] instanceOf CK\MARCspec\ComparisonStringInterface))
            {
                $leftSubTermReference = new File_MARC_Reference($subSpec['leftSubTerm']->__toString(),$this->record,$this->cache);
                
                $this->cache = array_replace($this->cache,$leftSubTermReference->cache);

                if(!$leftSubTermReference->content) // see 2.3.4 SubSpec validation
                {
                print "\n subspec $subSpec is FALSE (left no content)\n";
                    return $this->cache['validation'][$this->baseSubSpec] = false;
                }
                
                $leftSubTerm = $leftSubTermReference->content; // content maybe an empty array
            }
            else // is a CK\MARCspec\ComparisonStringInterface
            {
                $leftSubTerm[] = $subSpec['leftSubTerm']['comparable'];
            }
        }


        if(false === ($subSpec['rightSubTerm'] instanceOf CK\MARCspec\ComparisonStringInterface))
        {
            $rightSubTermReference = new File_MARC_Reference($subSpec['rightSubTerm']->__toString(),$this->record,$this->cache);
            
            $this->cache = array_replace($this->cache,$rightSubTermReference->cache);

            $rightSubTerm = $rightSubTermReference->content; // content maybe an empty array
        }
        else // is a CK\MARCspec\ComparisonStringInterface
        {
            $rightSubTerm[] = $subSpec['rightSubTerm']['comparable'];
        }
        
        $this->cache['validation'][$this->baseSubSpec] = false;
        
        switch($subSpec['operator'])
        {
            case '=':
            var_dump($leftSubTerm);
                if(0 < count(array_intersect($leftSubTerm,$rightSubTerm)))
                {
                    $this->cache['validation'][$this->baseSubSpec] = true;
                }
                break;
            
            case '!=':
                if(0 < count(array_diff($leftSubTerm,$rightSubTerm)))
                {
                    $this->cache['validation'][$this->baseSubSpec] = true;
                }
                break;
            
            case '~':
                if(0 <
                    count(
                        array_uintersect(
                            $leftSubTerm,
                            $rightSubTerm,
                            function($v1,$v2)
                            {
                                if (strpos($v1,$v2) !== false) return 0;
                                return -1;
                            }
                        )
                    )
                )
                {
                    $this->cache['validation'][$this->baseSubSpec] = true;
                }
                break;
            
            case '!~':
                if(0 <
                    count(
                        array_uintersect(
                            $leftSubTerm,
                            $rightSubTerm,
                            function($v1,$v2)
                            {
                                if (strpos($v1,$v2) === false) return 0;
                                return -1;
                            }
                        )
                    )
                )
                {
                    $this->cache['validation'][$this->baseSubSpec] = true;
                }
                break;
            
            case '?':
                if($rightSubTerm) 
                {
                    $this->cache['validation'][$this->baseSubSpec] = true;
                } 
                break;
            
            case '!':
                if(!$rightSubTerm) 
                {
                    $this->cache['validation'][$this->baseSubSpec] = true;
                }
                break;
        }
print "\nRETURN FROM SUBSPEC: $subSpec to".$this->baseSpec. " with validation result\n";
var_dump($this->cache['validation'][$this->baseSubSpec]);
        return $this->cache['validation'][$this->baseSubSpec];
    }
    
    /**
     * Reference fields by field tag
     * 
     * @return array Array of referred fields
     */
    private function referenceFieldsByTag()
    {
        $tag = $this->spec['field']['tag'];
        
        if(array_key_exists($tag,$this->cache)) return $this->cache[$tag];

        if('LDR' !== $tag)
        {
            $_fieldRef = $this->record->getFields($tag,true);
        }
        else // tag = LDR
        {
            $_fieldRef[] = $this->record->getLeader();
        }

        $this->cache[$tag] = $_fieldRef;

        return $_fieldRef;
    }
    
    /**
     * Reference fields. Filter by index and indicator
     * 
     * @return array $_fieldRef Array of referenced fields
     */
    private function referenceFields()
    {
        if(array_key_exists($this->baseSpec,$this->cache))
        {
            return $this->fields = $this->cache[$this->baseSpec];
        }
        
        if(!$this->fields = $this->referenceFieldsByTag()) return;
        
        /** filter on indizes */
        if($_indexRange = $this->getIndexRange($this->spec['field'],count($this->fields)))
        {
            $this->fields = array_intersect_key($this->fields,array_flip($_indexRange));
        }
        
        /** filter on indicators */
        if($this->spec['field']->offsetExists('indicator1') || $this->spec['field']->offsetExists('indicator2'))
        {
            foreach($this->fields as $key => $field)
            {
                // only filter by indicators for data fields
                if($field->isDataField())
                {
                    if($this->spec['field']->offsetExists('indicator1'))
                    {
                        if($field->getIndicator(1) != $this->spec['field']['indicator1'])
                        {
                            unset($this->fields[$key]);
                            continue;
                        }
                    }

                    if($this->spec['field']->offsetExists('indicator2'))
                    {
                        if($field->getIndicator(2) != $this->spec['field']['indicator2'])
                        {
                            unset($this->fields[$key]);
                            continue;
                        }
                    }
                    
                }
                else // control field have no indicators
                {
                    unset($this->fields[$key]);
                    continue;
                }
            }
        }
    }
    
    /**
     * Reference subfield contents and filter by index
     * 
     * @return array $_subfieldRef An array of referenced subfields
     */
    private function referenceSubfields()
    {
        if(array_key_exists($this->baseSubfieldSpec,$this->cache))
        {
            return $this->cache[$this->baseSubfieldSpec];
        }
        
        $_subfields = $this->field->getSubfields($this->currentSubfieldSpec['tag']);

        if(!$_subfields)
        {
                print "\nWRITING CACHE D $this->baseSubfieldSpec\n";
            $this->cache[$this->baseSubfieldSpec] = [];
            return [];
        }
        
        /** filter on indizes */
        if($_indexRange = $this->getIndexRange($this->currentSubfieldSpec,count($_subfields)))
        {
            foreach($_subfields as $sfkey => $item)
            {
                if(!in_array($sfkey,$_indexRange) || $item->isEmpty())
                {
                    unset($_subfields[$sfkey]);
                }
            }
        }
        
        if($_subfields)
        {
                print "\nWRITING CACHE E $this->baseSubfieldSpec\n";
            return $this->cache[$this->baseSubfieldSpec] = array_values($_subfields);
        }
        print "\nWRITING CACHE F $this->baseSubfieldSpec\n";
        $this->cache[$this->baseSubfieldSpec] = [];
        return [];
    }
    
    /**
     * Calculates a range from indexStart and indexEnd
     * 
     * @param array $spec The spec with possible indizes
     * @param int $total Total count of (sub)fields
     * 
     * @return array The range of indizes
     */
    private function getIndexRange($spec,$total)
    {
        $lastIndex = $total - 1;
        
        if($lastIndex < $spec['indexStart'])
        {
            return [$spec['indexStart']]; // this will result to no hits
        }
        
        $indexEnd = ('#' === $spec['indexEnd']) ? $lastIndex : $spec['indexEnd'];
        
        if($indexEnd > $lastIndex) $indexEnd = $lastIndex;

        return range($spec['indexStart'],$indexEnd);
    }
    
    /**
     * Updates $this->data and $this->content with $data
     * 
     * @param File_MARC_Field|File_MARC_Subfield|array[File_MARC_Field|File_MARC_Subfield] $data The data to add
     * @param CK\MARCspec\FieldInterface|CK\MARCspec\SubfieldInterface $spec The corresponding spec 
     */
    private function setDataContent($data,$spec,$by)
    {
        print "\nsetDataContent GOT\n";
        var_dump($data);
        if(!$data) return;
        
        $this->currentSpec = $spec;
        
        array_push($this->data,$data);
        array_push($this->content,$this->getContents($data));
        
        print "\nsetDataContent DATA by $by\n";
        var_dump($this->data);
        
        print "\nsetDataContent Content\n";
        var_dump($this->content);
        print "\n\n\n";
    }
    
    /**
     * Get the data content
     * Calls getSubstring to retrieve the substrings of content data depending on current spec.
     * 
     * @param string|File_MARC_Field|File_MARC_Subfield $data MARC data
     * 
     * @return string Data content
     */
    private function getContents($data)
    {
        // leader
        if(is_string($data))  return $this->getSubstring($data);

        if($data instanceOf File_MARC_Data_Field) return $this->getSubstring($data->toRaw());
        
        return $this->getSubstring($data->getData());
    }
    
    /**
     * Get the substring of data content depending on char start and length
     * 
     * @param string $content The data content
     * 
     * @return string The substring of data content
     */ 
    private function getSubstring($content)
    {
    print "\ngetSubstring got $content\n";
        
        if($this->currentSpec->offsetExists('charStart'))
        {
            $charStart = $this->currentSpec['charStart'];

            $length = $this->currentSpec['charLength'] * 1;
            
            if('#' === $charStart)
            {
                // negative index
                $charStart = $length * -1;
            }

            $content = substr($content,$charStart,$length);

        }
print "\ngetSubstring return $content\n";
        return $content;
    }
}
