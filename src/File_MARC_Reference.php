<?php

//namespace CK\File_MARC_Reference;

//use CK\MARCspec\MARCspec;

class File_MARC_Reference
{
    /**
     * @var File_MARC_Record The MARC record
     */ 
    private $record;

    /**
     * @var string|MARCspecInterface The MARCspec
     */ 
    private $spec;
    
    /**
     * @var string The current subfield tag
     */ 
    private $subfieldTag;
    
    /**
     * @var string The current spec processed
     */ 
    private $currentSpec;
    
    /**
     * @var array of fields
     */
    private $fields;

    /**
     * @var array[spec] Associative array cache of referred data
     */ 
    public $cache;

    /**
     * @var array[File_MARC_Field|File_MARC_Subfield] Data referred
     */ 
    public $data = false;
    
    /**
     * @var string|bool Data content referred
     */ 
    public $content = false;
    
    /**
     * Constructor
     * 
     * @param string|MARCspecInterface $spec The MARCspec
     * @param File_MARC_Record $record The MARC record
     */ 
    function __construct($spec, \File_MARC_Record $record, $cache = array())
    {
        $this->record = $record;
        
        $this->cache = $cache;

        if(is_string($spec))
        {
            $this->spec = new CK\MARCspec\MARCspec($spec);
        }
        else
        {
            $this->spec = $spec;
        }

        $this->interpreteSpec();
    }

    /**
     * Interpretes the MARCspec to decide what methods to call
     */
    private function interpreteSpec()
    {
        $valid = true;
        
        $this->currentSpec = $this->spec['field'];
        
        if($this->currentSpec->offsetExists('subSpecs'))
        {
            foreach($this->currentSpec['subSpecs'] as $_subSpec) // AND
            {
                if(is_array($_subSpec)) // OR
                {
                    foreach($_subSpec as $subSpec)
                    {
                        if($valid = $this->validateSubSpec($subSpec)) break; // at least one of them is true (OR)
                    }
                }
                else
                {
                    if(!$valid = $this->validateSubSpec($_subSpec)) break; // all of them have to be true (AND)
                }
            }
        }
        
        if($valid)
        {
            if($this->spec->offsetExists('subfields'))
            {
                if(!$this->fields = $this->referenceFields()) return;
                
                foreach($this->spec['subfields'] as $key => $this->currentSpec)
                {
                    $valid = true;
                    
                    // SubSpec Validation
                    if($this->currentSpec->offsetExists('subSpecs'))
                    {
                        foreach($this->currentSpec['subSpecs'] as $_subSpec)
                        {
                            if(is_array($_subSpec)) // chained subSpecs (OR)
                            {
                                foreach($_subSpec as $subSpec)
                                {
                                    if($valid = $this->validateSubSpec($subSpec)) break; // at least one of them is true
                                }
                            }
                            else // repeated SubSpecs (AND)
                            {
                                if(!$valid = $this->validateSubSpec($_subSpec)) break; // all of them have to be true (AND)
                            }
                        }
                    }
                    
                    if($valid) // subfield subSpecs are valid
                    {
                        $_subfields = $this->referenceSubfields();

                        if($_subfields)
                        {
                            if(is_array($this->data))
                            {
                                $this->data = array_merge($this->data,$_subfields);
                            }
                            else
                            {
                                $this->data = $_subfields;
                            }
                            
                            if(is_array($this->content))
                            {
                                $this->content = array_merge($this->content,$this->getContents($_subfields));
                            }
                            else
                            {
                                $this->content = $this->getContents($_subfields);
                            }
                            
                            $this->cacheControl($this->spec['field']['tag'].$this->currentSpec->__toString(),$this->content);
                        }
                    }
                } // end foreach subfield
            }
            else // Only a field spec
            {
                $this->data = $this->referenceFields();

                if($this->data)
                {
                    $this->content = $this->getContents($this->data);
                }
                
                $this->cacheControl($this->currentSpec->__toString(),$this->content);
            }
            
            
        } // field subspecs valid
    }
    
    /**
     * Validates a subSpec
     * 
     * @return bool True if subSpec is validm false if not
     */ 
    private function validateSubSpec($subSpec)
    {
        $validation = false;
        
        if(false === ($subSpec['leftSubTerm'] instanceOf CK\MARCspec\ComparisonStringInterface))
        {
            $leftSubTermRefrerence = new File_MARC_Reference($subSpec['leftSubTerm'],$this->record,$this->cache);

            foreach($leftSubTermRefrerence->cache as $key => $value)
            {
                $this->cache[$key] = $value;
            }
            
            if(!$leftSubTermRefrerence->data) return false; // see 2.3.4 SubSpec validation

            if(is_string($leftSubTermRefrerence->content))
            {
                $leftSubTerm[] = $leftSubTermRefrerence->content;
            }
            else
            {
                $leftSubTerm = $leftSubTermRefrerence->content;
            }
        }
        else // is a CK\MARCspec\ComparisonStringInterface
        {
            $leftSubTerm[] = $subSpec['leftSubTerm']['comparable'];
        }
        
        if(false === ($subSpec['rightSubTerm'] instanceOf CK\MARCspec\ComparisonStringInterface))
        {
            $rightSubTermRefrerence = new File_MARC_Reference($subSpec['rightSubTerm'],$this->record,$this->cache);
            
            foreach($rightSubTermRefrerence->cache as $key => $value)
            {
                $this->cache[$key] = $value;
            }
            #$this->cache = array_merge_recursive($this->cache,$rightSubTermRefrerence->cache);
            
            if(!$rightSubTermRefrerence->content || is_string($rightSubTermRefrerence->content))
            {
                $rightSubTerm[] = $rightSubTermRefrerence->content;
            }
            else
            {
                $rightSubTerm = $rightSubTermRefrerence->content;
            }
        }
        else // is a CK\MARCspec\ComparisonStringInterface
        {
            $rightSubTerm[] = $subSpec['rightSubTerm']['comparable'];
        }
        
        switch($subSpec['operator'])
        {
            case '=':
                if(0 < count(array_intersect($leftSubTerm,$rightSubTerm)))
                {
                    $validation = true;
                }
                break;
            
            case '!=':
                if(0 < count(array_diff($leftSubTerm,$rightSubTerm)))
                {
                    $validation = true;
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
                    $validation = true;
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
                    $validation = true;
                }
                break;
            
            case '?': 
                if(is_array($rightSubTerm))
                {
                    if($rightSubTerm[0]) $validation = true;
                }
                elseif($rightSubTerm)
                {
                    $validation = true;
                }
                
                break;
            
            case '!':
                if(is_array($rightSubTerm))
                {
                    if(!$rightSubTerm[0]) $validation = true;
                }
                elseif(!$rightSubTerm)
                {
                    $validation = true;
                }
                break;
        }

        return $validation;
    }
    
    /**
     * Reference fields by field tag
     * 
     * @return array Array of referred fields
     */
    private function referenceFieldsByTag()
    {
        $tag = $this->currentSpec['tag'];
        
        //if(array_key_exists($tag,$this->cache)) return $this->cache[$tag];
        if($this->cacheControl($tag)) return $this->cache[$tag];

        if('LDR' !== $tag)
        {
            $_fieldRef = $this->record->getFields($tag,true);
        }
        else // tag = LDR
        {
            $_fieldRef[] = $this->record->getLeader();
        }
        
        #$this->cache[$tag] = $_fieldRef;
        $this->cacheControl($tag,$_fieldRef);
        return $_fieldRef;
    }
    
    /**
     * Reference fields. Filter by index and indicator
     * 
     * @return array $_fieldRef Array of referenced fields
     */
    private function referenceFields()
    {
        $fieldspec = $this->currentSpec->__toString();
        
        if($this->cacheControl($fieldspec)) return $this->cache[$fieldspec];
        
        $_fieldRef = $this->referenceFieldsByTag();
        
        if($_fieldRef && !is_string($_fieldRef))
        {
        
            /** filter on indizes */
            $_indexRange = $this->getIndexRange($this->currentSpec,count($_fieldRef));

            if($_indexRange)
            {
                foreach($_fieldRef as $key => $field)
                {
                    if(!in_array($key,$_indexRange)) unset($_fieldRef[$key]);
                }
            }
            
            /** filter on indicators */
            if($this->currentSpec->offsetExists('indicator1') || $this->currentSpec->offsetExists('indicator2'))
            {
                foreach($_fieldRef as $key => $field)
                {
                    // only filter by indicators for data fields
                    if($field->isDataField())
                    {
                        if($this->currentSpec->offsetExists('indicator1'))
                        {
                            if($field->getIndicator(1) != $this->currentSpec['indicator1'])
                            {
                                unset($_fieldRef[$key]);
                            }
                        }

                        if($this->currentSpec->offsetExists('indicator2'))
                        {
                            if($field->getIndicator(2) != $this->currentSpec['indicator2'])
                            {
                                unset($_fieldRef[$key]);
                            }
                        }
                        
                    }
                    else // control field have no indicators
                    {
                        unset($_fieldRef[$key]);
                    }
                }
            }
            
            $_fieldRef = array_values($_fieldRef);
        }
        
        $this->cacheControl($fieldspec,$_fieldRef);
        
        return (0 < count($_fieldRef)) ? $_fieldRef : false;
    }
    
    /**
     * Reference subfield contents and filter by index
     * 
     * @return array $_subfieldRef An array of referenced subfields
     */
    private function referenceSubfields()
    {
        
        $_subfieldRef = false;
        
        $_subfieldCollection = array();
        
        foreach($this->fields as $field)
        {
            $currentSpec = $this->spec['field']->__toString().'$'.$this->currentSpec['tag'];
            
            //if(array_key_exists($currentSpec,$this->cache)) return $this->cache[$currentSpec];
            if($this->cacheControl($currentSpec)) return $this->cache[$currentSpec];
            
            $_subfields = array();
            
            if($field->isDataField()) // control fields will be skipped
            {
                $_subfields = $field->getSubfields($this->currentSpec['tag']);
                
                /** filter on indizes */
                $_indexRange = $this->getIndexRange($this->currentSpec,count($_subfields));
                
                if($_indexRange)
                {
                    foreach($_subfields as $key => $item)
                    {
                        if(!in_array($key,$_indexRange) || $item->isEmpty())
                        {
                            unset($_subfields[$key]);
                        }
                    }
                }
            }
            
            if(0 < count($_subfields))
            {
                $_subfieldCollection = array_merge($_subfieldCollection,array_values($_subfields));
            }
        }
        
        if(0 < count($_subfieldCollection))
        {
            $_subfieldRef = $_subfieldCollection;
        }

        #$this->cache[$currentSpec] = $_subfieldRef;
        $this->cacheControl($currentSpec,$_subfieldRef);
        
        return $_subfieldRef;
    }
    
    /**
     * Calculates a range from indexStart and indexEnd
     * 
     * @param array $spec The spec with possible indizes
     * @param int $total Total count of fields
     * 
     * @return array $_indexRange The range of indizes
     */
    private function getIndexRange($spec,$total)
    {
        $_indexRange = false;
        
        if($spec->offsetExists('indexStart'))
        {
            if($spec->offsetExists('indexEnd'))
            {
                $lastIndex = $total - 1;
                
                $indexEnd = ('#' === $spec['indexEnd']) ? $lastIndex : $spec['indexEnd'];
                
                if($indexEnd > $lastIndex) $indexEnd = $lastIndex;
            }
            else
            {
                $indexEnd = $spec['indexStart'];
            }
            
            $_indexRange = range($spec['indexStart'],$indexEnd);
        }
        
        return $_indexRange;
    }
    
    /**
     * Get the data content
     * Calls getSubstring to retrieve the substrings of content data depending on current spec.
     * 
     * @param array[string|File_MARC_Field|File_MARC_Subfield] Array of MARC data
     * 
     * @return array $_content Array of data content
     */
    private function getContents($_data)
    {
        $_content = array();
        
        foreach($_data as $key => $data)
        {
            if(is_string($data)) // leader
            {
                $_content[$key] = $this->getSubstring($data);
            }
            elseif($data instanceOf File_MARC_Data_Field)
            {
                $_content[$key] = $this->getSubstring($data->toRaw());
            }
            else
            {
                $_content[$key] = $this->getSubstring($data->getData());
            }
        }
        
        return $_content;
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
        if($this->currentSpec->offsetExists('charStart'))
        {
            $charStart = $this->currentSpec['charStart'];
            
            if($this->currentSpec->offsetExists('charLength'))
            {
                $length = $this->currentSpec['charLength'] * 1;
                
                if('#' === $charStart)
                {
                    // negative index
                    $charStart = $length * -1;
                }

                $content = substr($content,$charStart,$length);
            }
            else
            {
                $content = substr($content,$charStart);
            }
        }

        return $content;
        
    }
    
    private function cacheControl($key,$cached = false)
    {
        if(array_key_exists($key,$this->cache))
        {
            return true;
        }
        elseif($cached)
        {
            $this->cache[$key] = $cached;
            return true;
        }
        return false;
    }
}
// }}}