<?php
//
// Definition of eZTemplateCacheFunction class
//
// Created on: <28-Feb-2003 15:06:33 bf>
//
// Copyright (C) 1999-2004 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE.GPL included in
// the packaging of this file.
//
// Licencees holding valid "eZ publish professional licences" may use this
// file in accordance with the "eZ publish professional licence" Agreement
// provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" is available at
// http://ez.no/products/licences/professional/. For pricing of this licence
// please contact us via e-mail to licence@ez.no. Further contact
// information is available at http://ez.no/home/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/*!
  \class eZTemplateCacheFunction eztemplatecachefunction.php
  \ingroup eZTemplateFunctions
  \brief Advanced cache handling

*/

class eZTemplateCacheFunction
{
    /*!
     Initializes the object with names.
    */
    function eZTemplateCacheFunction( $blockName = 'cache-block' )
    {
        $this->BlockName = $blockName;
    }

    /*!
     Returns an array containing the name of the block function, default is "block".
     The name is specified in the constructor.
    */
    function functionList()
    {
        return array( $this->BlockName );
    }

    function functionTemplateHints()
    {
        return array( $this->BlockName => array( 'parameters' => true,
                                                 'static' => false,
                                                 'tree-transformation' => true ) );
    }

    function templateNodeTransformation( $functionName, &$node,
                                         &$tpl, &$resourceData, $parameters )
    {
        $ini =& eZINI::instance();
        $children = eZTemplateNodeTool::extractFunctionNodeChildren( $node );
        if ( $ini->variable( 'TemplateSettings', 'TemplateCache' ) == 'disabled' )
        {
            return $children;
        }

        $functionPlacement = eZTemplateNodeTool::extractFunctionNodePlacement( $node );
        $placementKeyString  = $functionPlacement[0][0] . "_";
        $placementKeyString .= $functionPlacement[0][1] . "_";
        $placementKeyString .= $functionPlacement[1][0] . "_";
        $placementKeyString .= $functionPlacement[1][1] . "_";
        $placementKeyString .= $functionPlacement[2] . "_";

        $newNodes = array();

        $expiry = 60*60*2;
        if ( isset( $parameters['expiry'] ) )
        {
            if ( eZTemplateNodeTool::isStaticElement( $parameters['expiry'] ) )
            {
                $expiryText = eZPHPCreator::variableText( eZTemplateNodeTool::elementStaticValue( $parameters['expiry'] ) , 0, 0, false );
            }
            else
            {
                $newNodes[] = eZTemplateNodeTool::createVariableNode( false, $parameters['expiry'], false, array(),
                                                                      'localExpiry' );
                $expiryText = "\$localExpiry";
            }
        }
        else
        {
            $expiryText = eZPHPCreator::variableText( $expiry , 0, 0, false );
        }
        $localExpiryText = "( mktime() - $expiryText )";

        $keysData = false;
        $keyValue = false;
        $keyValueText = false;
        $useDynamicKeys = false;
        if ( isset( $parameters['keys'] ) )
        {
            $keysData = $parameters['keys'];
            $keysDataInspection = eZTemplateCompiler::inspectVariableData( $tpl,
                                                                           $keysData, false,
                                                                           $resourceData );
            if ( !$keysDataInspection['is-constant'] or
                 $keysDataInspection['has-operators'] or
                 $keysDataInspection['has-attributes'] )
            {
                $useDynamicKeys = true;
            }
            else
            {
                $keyValue = $keysDataInspection['new-data'][0][1];
                $keyValueText = $keyValue . '_';
            }
        }
        if ( $useDynamicKeys )
        {
            $accessName = false;
            if ( isset( $GLOBALS['eZCurrentAccess']['name'] ) )
                $accessName = $GLOBALS['eZCurrentAccess']['name'];
            $extraKeyString = $placementKeyString . $accessName;
            $extraKeyText = eZPHPCreator::variableText( $extraKeyString, 0, 0, false );
            $newNodes[] = eZTemplateNodeTool::createVariableNode( false, $keysData, false, array(),
                                                                  'cacheKeys' );
            $newNodes[] = eZTemplateNodeTool::createCodePieceNode( "if ( is_array( \$cacheKeys ) )\n    \$cacheKeys = implode( '_', \$cacheKeys ) . '_';\nelse\n    \$cacheKeys .= '_';" );
            $cacheDir = eZSys::cacheDirectory();
            $cachePathText = eZPHPCreator::variableText( "$cacheDir/template-block/", 0, 0, false );
            $code = "\$keyString = (string) crc32( \$cacheKeys . $extraKeyText );\n\$cacheDir = $cachePathText . \$keyString[0] . '/' . \$keyString[1] . '/' . \$keyString[2];\n\$cachePath = \$cacheDir . '/' . \$keyString . '.php';";
            $newNodes[] = eZTemplateNodeTool::createCodePieceNode( $code );
            $filedirText = "\$cacheDir";
            $filepathText = "\$cachePath";
        }
        else
        {
            $accessName = false;
            if ( isset( $GLOBALS['eZCurrentAccess']['name'] ) )
                $accessName = $GLOBALS['eZCurrentAccess']['name'];
            $keyString = (string) crc32( $keyValueText . $placementKeyString . $accessName );
            $cacheDir = eZSys::cacheDirectory();
            $dirString = "$cacheDir/template-block/" . $keyString[0] . '/' . $keyString[1] . '/' . $keyString[2];
            $keyString = "$cacheDir/template-block/" . $keyString[0] . '/' . $keyString[1] . '/' . $keyString[2] . '/' . $keyString . '.php';
            $filedirText = eZPHPCreator::variableText( $dirString, 0, 0, false );
            $filepathText = eZPHPCreator::variableText( $keyString, 0, 0, false );
        }
        $code = ( "if ( file_exists( $filepathText ) and\n" .
                  "     filemtime( $filepathText ) >= $localExpiryText )\n" .
                  "{\n" .
                  "    include( $filepathText );" );
        $newNodes[] = eZTemplateNodeTool::createCodePieceNode( $code, array( 'spacing' => 0 ) );
        $newNodes[] = eZTemplateNodeTool::createWriteToOutputVariableNode( 'contentData', array( 'spacing' => 4 ) );
        $newNodes[] = eZTemplateNodeTool::createCodePieceNode( "    unset( \$contentData );\n" .
                                                               "}\n" .
                                                               "else\n" .
                                                               "{" );
        $newNodes[] = eZTemplateNodeTool::createOutputVariableIncreaseNode( array( 'spacing' => 4 ) );
        $newNodes[] = eZTemplateNodeTool::createSpacingIncreaseNode( 4 );
        $newNodes = array_merge( $newNodes, $children );
        $newNodes[] = eZTemplateNodeTool::createSpacingDecreaseNode( 4 );
        $newNodes[] = eZTemplateNodeTool::createAssignFromOutputVariableNode( 'cachedText', array( 'spacing' => 4 ) );
        $ini =& eZINI::instance();
        $perm = octdec( $ini->variable( 'FileSettings', 'StorageDirPermissions' ) );
        $code = ( "include_once( 'lib/ezfile/classes/ezdir.php' );\n" .
                  "eZDir::mkdir( $filedirText, $perm, true );\n" .
                  "\$fd = fopen( $filepathText, 'w' );\n" .
                  "fwrite( \$fd, '<?' );\n" .
                  "fwrite( \$fd, \"php\\n\\\$contentData = \\\"\" );\n" .
                  "fwrite( \$fd, " . 'str_replace( array( "\\\\", "\\"", "\\$", "\\n" ),' . "\n" .
                  '                           array( "\\\\\\\\", "\\\\\\"", "\\\\$", "\\\\n" ),' . "\n" .
                  '                                  $cachedText' . " ) );\n" .
                  "fwrite( \$fd, \"\\\";\\n?>\\n\" );\n" .
                  "fclose( \$fd );");
        $newNodes[] = eZTemplateNodeTool::createCodePieceNode( $code, array( 'spacing' => 4 ) );
        $newNodes[] = eZTemplateNodeTool::createOutputVariableDecreaseNode( array( 'spacing' => 4 ) );
        $newNodes[] = eZTemplateNodeTool::createWriteToOutputVariableNode( 'cachedText', array( 'spacing' => 4 ) );
        $newNodes[] = eZTemplateNodeTool::createCodePieceNode( "    unset( \$cachedText );\n}" );
        return $newNodes;
    }

    /*!
     Processes the function with all it's children.
    */
    function process( &$tpl, &$textElements, $functionName, $functionChildren, $functionParameters, $functionPlacement, $rootNamespace, $currentNamespace )
    {
        switch ( $functionName )
        {
            case $this->BlockName:
            {
                // Check for disabled cache.
                $ini =& eZINI::instance();
                if ( $ini->variable( 'TemplateSettings', 'TemplateCache' ) == 'disabled' )
                {
                    $children = $functionChildren;

                    $childTextElements = array();
                    foreach ( array_keys( $children ) as $childKey )
                    {
                        $child =& $children[$childKey];
                        $tpl->processNode( $child, $childTextElements, $rootNamespace, $currentNamespace );
                    }
                    $text =& implode( '', $childTextElements );
                    $textElements[] = $text;
                }
                else
                {
                    $keyString = "";
                    // Get cache keys
                    if ( isset( $functionParameters["keys"] ) )
                    {
                        $keys = $tpl->elementValue( $functionParameters["keys"], $rootNamespace, $currentNamespace, $functionPlacement );

                        if ( is_array( $keys ) )
                        {
                            foreach ( $keys as $key )
                            {
                                $keyString .= $key . "_";
                            }
                        }
                        else
                        {
                            $keyString .= $keys . "_";
                        }
                    }

                    // Append keys from position in template
                    $keyString .= $functionPlacement[0][0] . "_";
                    $keyString .= $functionPlacement[0][1] . "_";
                    $keyString .= $functionPlacement[1][0] . "_";
                    $keyString .= $functionPlacement[1][1] . "_";
                    $keyString .= $functionPlacement[2] . "_";

                    // Fetch the current siteaccess
                    $accessName = false;
                    if ( isset( $GLOBALS['eZCurrentAccess']['name'] ) )
                        $accessName = $GLOBALS['eZCurrentAccess']['name'];
                    $keyString .= $accessName;
                    $hashedKey = (string) crc32( $keyString );

                    $cacheDir = eZSys::cacheDirectory();
                    $phpDir = "$cacheDir/template-block/" . $hashedKey[0] . "/" . $hashedKey[1] . "/" . $hashedKey[2];
                    $phpPath = $phpDir . '/' . $hashedKey . ".php";

                    // Check if a custom expiry time is defined
                    if ( isset( $functionParameters["expiry"] ) )
                    {
                        $expiry = $tpl->elementValue( $functionParameters["expiry"], $rootNamespace, $currentNamespace, $functionPlacement );
                    }
                    else
                    {
                        // Default expiry time is set to two hours
                        $expiry = 60*60*2;
                    }

                    $localExpiryTime = mktime() - $expiry;

                    $ignoreContentExpiry = false;
                    if ( isset( $functionParameters["ignore_content_expiry"] ) )
                    {
                        $ignoreContentExpiry = $tpl->elementValue( $functionParameters["ignore_content_expiry"], $rootNamespace, $currentNamespace, $functionPlacement ) === true;
                    }

                    $expiryTime = $localExpiryTime;
                    if ( $ignoreContentExpiry == false )
                    {
                        include_once( 'lib/ezutils/classes/ezexpiryhandler.php' );
                        $handler =& eZExpiryHandler::instance();
                        if ( $handler->hasTimestamp( 'content-cache' ) )
                        {
                            $globalExpiryTime = $handler->timestamp( 'content-cache' );
                            $expiryTime = max( $localExpiryTime, $globalExpiryTime );
                        }
                    }

                    // Check if we can restore
                    if ( file_exists( $phpPath ) and
                         filemtime( $phpPath ) >= $expiryTime )
                    {
                        include( $phpPath );
                        $textElements[] = $contentData;
                    }
                    else
                    {
                        // If no cache or expired cache, load data
                        $children = $functionChildren;

                        $childTextElements = array();
                        foreach ( array_keys( $children ) as $childKey )
                        {
                            $child =& $children[$childKey];
                            $tpl->processNode( $child, $childTextElements, $rootNamespace, $currentNamespace );
                        }
                        $text =& implode( '', $childTextElements );
                        $textElements[] = $text;

                        include_once( 'lib/ezfile/classes/ezdir.php' );
                        $ini =& eZINI::instance();
                        $perm = octdec( $ini->variable( 'FileSettings', 'StorageDirPermissions' ) );
                        eZDir::mkdir( $phpDir, $perm, true );
                        $fd = fopen( $phpPath, 'w' );
                        fwrite( $fd, '<?' );
                        fwrite( $fd, "php\n\$contentData = \"" );
                        fwrite( $fd, str_replace( array( "\\", "\"", "\$", "\n" ),
                                                  array( "\\\\", "\\\"", "\\$", "\\n" ),
                                                  $text ) );
                        fwrite( $fd, "\";\n?>\n" );
                        fclose( $fd );
                    }
                }
            } break;
        }
    }

    /*!
     Returns true.
    */
    function hasChildren()
    {
        return true;
    }

    /// \privatesection
    /// Name of the function
    var $Name;
}

?>
