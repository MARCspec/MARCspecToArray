<?php
/**
 * Parses a MARCspec into an array
 */ 
class MARCspecParser
{
    /**
     * @var string Regex for field tag
     */
    protected $FIELDTAG;
    /**
     * @var string Regex for position or range
     */
    protected $POSITIONORRANGE;
    /**
     * @var string Regex for index
     */
    protected $INDEX;
    /**
     * @var string Regex for charpos
     */
    protected $CHARPOS;
    /**
     * @var string Regex for indicators
     */
    protected $INDICATORS;
    /**
     * @var string Regex for field subspecs
     */
    protected $F_SUBSPECS;
    /**
     * @var string Regex for subfield subspecs
     */
    protected $SF_SUBSPECS;
    /**
     * @var string Regex for subfields
     */
    protected $SUBFIELDS;
    /**
     * @var string Regex for field
     */
    protected $FIELD;
    /**
     * @var string Regex for subfield range
     */
    protected $SUBFIELDTAGRANGE;
    /**
     * @var string Regex for subfield tag
     */
    protected $SUBFIELDTAG;
    /**
     * @var string Regex for subfield
     */
    protected $SUBFIELD;
    /**
     * @var string Regex for leftSubTerm
     */
    protected $LEFTSUBTERM;
    /**
     * @var string Regex for operator
     */
    protected $OPERATOR;
    /**
     * @var string Regex for subterms
     */
    protected $SUBTERMS;
    /**
     * @var string Regex for subspec
     */
    protected $SUBSPEC;
    
    /**
     * @var array The parsed MARCspec
     */
    public $parsed = [];
    
    /**
     * @var array The parsed fieldspec
     */
    public $field = [];
    
    /**
     * @var array The parsed subfieldspecs
     */
    public $subfields = [];
    
    public function __construct($spec = null)
    {
        $this->setConstants();
        
        if(is_null($spec)) return;
        
        $this->fieldToArray($spec);
        
        if(array_key_exists('subfields',$this->parsed))
        {
            $this->subfields = $this->matchSubfields($this->parsed['subfields']);
        }
    }
    
    /**
     * parses fieldspecs into array 
     * 
     * @param string $fieldspec The fieldspec
     * @return array An Array of fieldspec
     */ 
    public function fieldToArray($fieldspec)
    {
        $_fieldGroups = ['field','tag','index','charpos','indicators','subfields'];
        
        if(!preg_match_all('/'.$this->FIELD.'/',$fieldspec,$_fieldMatches,PREG_SET_ORDER))
        {
            throw new UnexpectedValueException('Cannot detect fieldspec.');
        }

        $this->parsed = array_filter($_fieldMatches[0],'strlen');

        if(!array_key_exists('field',$this->parsed))
        {
            throw new UnexpectedValueException('For fieldtag only "." and digits and lowercase alphabetic or digits and upper case alphabetics characters are allowed');
        }
        
        if(strlen($this->parsed['field']) !== strlen($fieldspec))
        {
            throw new UnexpectedValueException('Detected useless data fragment.');
        }
        
        foreach($_fieldGroups as $fieldgroup)
        {
            if(array_key_exists($fieldgroup,$this->parsed))
            {
                $this->field[$fieldgroup] = $this->parsed[$fieldgroup];
            }
        }

        if(array_key_exists('charpos',$this->field))
        {
            if(array_key_exists('indicators',$this->field))
            {
                throw new UnexpectedValueException('Either characterSpec or indicators are allowed.');
            }
            
            if(array_key_exists('subfields',$this->field))
            {
                throw new UnexpectedValueException('Either characterSpec for field or subfields are allowed.');
            }
            
        }
        
        if(array_key_exists('subspecs',$this->parsed))
        {
            $_fieldSubSpecs = $this->matchSubSpecs($this->parsed['subspecs']);

            foreach($_fieldSubSpecs as $fieldSubSpec)
            {
                if(1 < count($fieldSubSpec))
                {
                    foreach($fieldSubSpec as $orSubSpec)
                    {
                        $_or[] = $this->matchSubTerms($orSubSpec);
                    }
                    $this->field['subspecs'][] = $_or;
                }
                else
                {
                    $this->field['subspecs'][] = $this->matchSubTerms($fieldSubSpec[0]);
                }
            }
        }
    }
    
    /**
    * Matches subfieldspecs
    * 
    * @param string $subfieldspec A string of one or more subfieldspecs
    */ 
    public function matchSubfields($subfieldspec)
    {
        if(!preg_match_all('/'.$this->SUBFIELD.'/',$subfieldspec,$_subfieldMatches,PREG_SET_ORDER))
        {
            throw new UnexpectedValueException('For subfields only digits, lowercase alphabetic characters or one of "!"#$%&\'()*+,-./0-9:;<=>?[\]^_`a-z{}~" are allowed.');
        }
        /**
        * For each subfield (array) do anonymous function
        * - first filter empty elements
        * - second look for subspecs
        * - match subspecs and match subTerms
        * - return everything in the array of subfields
        */
        array_walk(
            $_subfieldMatches,
            function(&$_subfield) use (&$test)
            {
                $_subfield = array_filter($_subfield,'strlen');
                
                $test .= $_subfield['subfield'];
                
                if(array_key_exists('subspecs',$_subfield))
                {
                    $_ss = [];
                    
                    if(!$_subfieldSubSpecs = $this->matchSubSpecs($_subfield['subspecs']))
                    {
                        // TODO: raise error;
                    }
                    
                    foreach($_subfieldSubSpecs as $key => $_subfieldSubSpec)
                    {
                        if(1 < count($_subfieldSubSpec))
                        {
                            foreach($_subfieldSubSpec as $orSubSpec)
                            {
                                $_or[] = $this->matchSubTerms($orSubSpec);
                            }
                            $_ss[] = $_or;
                        }
                        else
                        {
                            $_ss[] = $this->matchSubTerms($_subfieldSubSpec[0]);
                        }
                    }
                    
                    $_subfield['subspecs'] = $_ss;
                }
            }
        );

        if($test !== $subfieldspec)
        {
            throw new UnexpectedValueException('Detected useless data fragment.');
        }
        
        return $_subfieldMatches;
    }
    
    /**
    * calls matchSubfields but makes sure only one subfield is present 
    * 
    * @param string $subfieldspec A subfieldspec
    * @return array An Array of subfieldspec
    */ 
    public function subfieldToArray($subfieldspec)
    {
        if(!$_sf = $this->matchSubfields($subfieldspec))
        {
            throw new UnexpectedValueException('Assuming invalid spec.');
        }
        
        if(1 < count($_sf))
        {
            throw new UnexpectedValueException('Detected more than one subfieldspecs. Use method addSubfields to add more than one subfield.');
        }
        
        if($_sf[0]['subfield'] !== $subfieldspec)
        {
            throw new UnexpectedValueException('Detected useless data fragment.');
        }
        
        return $_sf[0];
    }
    
    /**
    * parses subspecs into an array
    * 
    * @param string $subSpecs One or more subspecs
    * @return array Array of subspecs
    */
    private function matchSubSpecs($subSpecs)
    {
        $_subSpecs = [];
        if(!preg_match_all('/'.$this->SUBSPEC.'/U',$subSpecs,$_subSpecMatches,PREG_SET_ORDER))
        {
            throw new UnexpectedValueException('Assuming invalid spec.');
        }

        foreach($_subSpecMatches as $key => $_subSpecMatch)
        {
            if(array_key_exists(1,$_subSpecMatch) && !empty($_subSpecMatch[1]))
            {
                $_subSpecs[$key] = preg_split('/(?<!\\\)\|/',$_subSpecMatch[1],-1,PREG_SPLIT_NO_EMPTY);
            }
            else
            {
                throw new UnexpectedValueException('Assuming invalid spec.');
            }
        }
        return $_subSpecs;
    }
    
    /**
    * Parses a single SubSpec into sunTerms
    * 
    * @param string $subSpec A single SubSpec
    * @return array subTerms as array
    */
    private function matchSubTerms($subSpec)
    {
        if(preg_match('/(?<![\\\\\$])[\{\}]/',$subSpec,$_error, PREG_OFFSET_CAPTURE))
        {
            throw new UnexpectedValueException('Unescaped character detected');
        }
        
        if(preg_match_all('/'.$this->SUBTERMS.'/',$subSpec,$_subTermMatches,PREG_SET_ORDER))
        {
            if(empty($_subTermMatches[0]['operator']))
            {
                $_subTermMatches[0]['operator'] = "?";
            }
            if(!$_subTermMatches[0]['rightsubterm'])
            {
                throw new UnexpectedValueException('Right hand subTerm is missing.');
            }
            return array_filter($_subTermMatches[0],'strlen');
        }
        else
        {
            throw new UnexpectedValueException('Assuming invalid spec.');
        }
    }
    
    /**
    * Set regex variables (constant)
    */
    private function setConstants()
    {
        $this->FIELDTAG = '^(?<tag>(?:[a-z0-9\.]{3,3}|[A-Z0-9\.]{3,3}|[0-9\.]{3,3}))?';
        $this->POSITIONORRANGE = '(?:(?:(?:[0-9]+|#)\-(?:[0-9]+|#))|(?:[0-9]+|#))';
        $this->INDEX = '(?:\[(?<index>'.$this->POSITIONORRANGE.')\])?';
        $this->CHARPOS = '\/(?<charpos>'.$this->POSITIONORRANGE.')';
        $this->INDICATORS = '_(?<indicators>(?:[_a-z0-9][_a-z0-9]{0,1}))';
        $this->SUBSPECS = '(?<subspecs>(?:\{.+?(?<!(?<!(\$|\\\))(\$|\\\))\})*)';
        #$this->SF_SUBSPECS = '(?<subspecs>(?:\{.+?\})*)';
        $this->SUBFIELDS = '(?<subfields>\$.+)?';
        $this->FIELD = '(?<field>(?:'.$this->FIELDTAG.$this->INDEX.'(?:'.$this->CHARPOS.'|'.$this->INDICATORS.')?'.$this->SUBSPECS.$this->SUBFIELDS.'))';
        $this->SUBFIELDTAGRANGE = '(?<subfieldtagrange>(?:[0-9a-z]\-[0-9a-z]))';
        $this->SUBFIELDTAG = '(?<subfieldtag>[\!-\?\[-\{\}-~])';
        $this->SUBFIELD = '(?<subfield>\$(?:'.$this->SUBFIELDTAGRANGE.'|'.$this->SUBFIELDTAG.')'.$this->INDEX.'(?:'.$this->CHARPOS.')?'.$this->SUBSPECS.')';
        $this->LEFTSUBTERM = '^(?<leftsubterm>(?:\\\(?:(?<=\\\)[\!\=\~\?]|[^\!\=\~\?])+)|(?:(?<=\$)[\!\=\~\?]|[^\!\=\~\?])+)?';
        $this->OPERATOR = '(?<operator>\!\=|\!\~|\=|\~|\!|\?)';
        $this->SUBTERMS = '(?:'.$this->LEFTSUBTERM.$this->OPERATOR.')?(?<rightsubterm>.+)$';
        $this->SUBSPEC = '(?:\{(.+)\})';
    }
}
