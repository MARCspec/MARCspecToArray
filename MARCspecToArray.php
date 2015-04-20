<?php

define('FIELDTAG','^(?<tag>[a-z0-9]{3,3}|[A-Z0-9]{3,3}|[0-9\.]{3,3})');
define('POSITIONORRANGE','(?:(?:(?:[0-9]+|#)\-(?:[0-9]+|#))|(?:[0-9]+|#))');
define('INDEX','(?<index>\['.POSITIONORRANGE.'\])?');
define('CHARPOS','(?<charpos>\/'.POSITIONORRANGE.')');
define('INDICATORS','(?<indicators>_[_a-z0-9][_a-z0-9]{0,1})');
define('SUBSPECS','(?<subspecs>(?:\{[^}]*\})*)');
define('SUBFIELDS','(?<subfields>\$.*)?');
define('FIELD','(?<field>(?:'.FIELDTAG.INDEX.'(?:'.CHARPOS.'|'.INDICATORS.')?'.SUBSPECS.SUBFIELDS.'))');
define('SUBFIELDTAGRANGE','(?<subfieldtagrange>\$(?:(?:[a-z]\-[a-z])|(?:[0-9]\-[0-9])))');
define('SUBFIELDTAG','\$(?<subfieldtag>[\!-\?\[-\{\}-~])');
define('SUBFIELD','(?<subfield>(?:'.SUBFIELDTAGRANGE.'|'.SUBFIELDTAG.')'.INDEX.CHARPOS.'?'.SUBSPECS.')');
define('LEFTSUBTERM','^(?<leftsubterm>(?:\\\(?:(?<=\\\)[\!\=\~\?]|[^\!\=\~\?])+)|(?:(?<=\$)[\!\=\~\?]|[^\!\=\~\?])+)?');
define('OPERATOR','(?<operator>\=|\~|\!|\?|\!\~|\!\=)');
define('SUBTERMS','(?:'.LEFTSUBTERM.OPERATOR.')?(?<rightsubterm>.+)$');
define('SUBSPEC','(\{([^}]+)\})');

function MARCspecToArray($string)
{
    $_fieldGroups = ['field','tag','index','charpos','indicators','subfields'];
    $_subfieldGroups = ['subfield','subfieldtag','subfieldtagrange','index','charpos'];
    if(preg_match_all('/'.FIELD.'/',$string,$_fieldMatches,PREG_SET_ORDER))
    {
        $_ms['field'] = [];
        foreach($_fieldGroups as $fieldgroup)
        {
            if(array_key_exists($fieldgroup,$_fieldMatches[0]) && !empty($_fieldMatches[0][$fieldgroup]))
            {
                $_ms['field'][$fieldgroup] = $_fieldMatches[0][$fieldgroup];
            }
        }
        if(array_key_exists('subspecs',$_fieldMatches[0]) && !empty($_fieldMatches[0]['subspecs']))
        {
            $_fieldSubSpecs = matchSubSpecs($_fieldMatches[0]['subspecs']);
            foreach($_fieldSubSpecs as $key => $_fieldSubSpec)
            {
                foreach($_fieldSubSpec as $fieldSubSpec)
                {
                    $_ms['field']['subspecs'][$key][] = subSpecToArray($fieldSubSpec);
                }
            }
        }
        if(array_key_exists('subfields',$_fieldMatches[0]))
        {
            $_ms['subfields'] = [];
            if(preg_match_all('/'.SUBFIELD.'/',$_fieldMatches[0]['subfields'],$_subfieldMatches,PREG_SET_ORDER))
            {
                foreach($_subfieldMatches as $key => $_subfieldMatch)
                {
                    foreach($_subfieldGroups as $subfieldgroup)
                    {
                        if(array_key_exists($subfieldgroup,$_subfieldMatch) && !empty($_subfieldMatch[$subfieldgroup]))
                        {
                            $_ms['subfields'][$key][$subfieldgroup] = $_subfieldMatch[$subfieldgroup];
                        }
                    }
                }
                if(array_key_exists('subspecs',$_subfieldMatch) && !empty($_subfieldMatch['subspecs']))
                {
                    #$_ms['subfields'][$key]['subspecs'][] = matchSubSpecs($_subfieldMatch['subspecs']);
                    $_subfieldSubSpecs = matchSubSpecs($_subfieldMatch['subspecs']);
                    foreach($_subfieldSubSpecs as $key => $_subfieldSubSpec)
                    {
                        foreach($_subfieldSubSpec as $subfieldSubSpec)
                        {
                            $_ms['subfields'][$key]['subspecs'][$key][] = subSpecToArray($subfieldSubSpec);
                        }
                    }
                }
            }
        }
        return $_ms;
    }
    
    return null;
}

function subSpecToArray($subSpec)
{
    $_subSpec = [];
    if(preg_match_all('/'.SUBTERMS.'/',$subSpec,$_subTermMatches,PREG_SET_ORDER))
    {
        if(array_key_exists('leftsubterm',$_subTermMatches[0]) && !empty($_subTermMatches[0]['leftsubterm']))
        {
            $_subSpec['leftsubterm'] = ('\\' == $_subTermMatches[0]['leftsubterm'][0]) ? $_subTermMatches[0]['leftsubterm'] : MARCspecToArray($_subTermMatches[0]['leftsubterm']);
        }
        if(array_key_exists('rightsubterm',$_subTermMatches[0]) && !empty($_subTermMatches[0]['rightsubterm']))
        {
            $_subSpec['rightsubterm'] = ('\\' == $_subTermMatches[0]['rightsubterm'][0]) ? $_subTermMatches[0]['rightsubterm'] : MARCspecToArray($_subTermMatches[0]['rightsubterm']);
        }
        if(array_key_exists('operator',$_subTermMatches[0]) && !empty($_subTermMatches[0]['operator']))
        {
            $_subSpec['operator'] = $_subTermMatches[0]['operator'];
        }
        return $_subSpec;
    }
    return null;
}

function matchSubSpecs($subSpecs)
{
    if(preg_match_all('/'.SUBSPEC.'/',$subSpecs,$_subSpecMatches,PREG_SET_ORDER))
    {
        foreach($_subSpecMatches as $key => $_subSpecMatch)
        {
            if($_subSpecMatch[2] && !empty($_subSpecMatch[2]))
            {
                foreach(explode('|',$_subSpecMatch[2]) as $altSubSpec)
                {
                    $_subSpecs[$key][] = $altSubSpec;
                }
            }
        }
        return $_subSpecs;
    }
    
    return null;
}
