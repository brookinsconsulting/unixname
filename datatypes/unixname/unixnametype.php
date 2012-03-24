<?php
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ publish UNIX name extension
// SOFTWARE RELEASE: 0.x
// COPYRIGHT NOTICE: Copyright (C) 2006-2007 SCK-CEN, Kristof Coomans
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//
include_once( 'kernel/classes/datatypes/ezstring/ezstringtype.php' );
define( 'EZ_DATATYPESTRING_UNIXNAME', 'unixname' );

class UnixNameType extends eZStringType
{
    function UnixNameType()
    {
        $this->eZDataType( EZ_DATATYPESTRING_UNIXNAME, ezi18n( 'kernel/classes/datatypes', 'UNIX name', 'Datatype name' ),
                           array( 'translation_allowed' => false,
                                  'serialize_supported' => true,
                                  'object_serialize_map' => array( 'data_text' => 'text',
                                                                   'data_int' => 'is_set' ) ) );
        $this->MaxLenValidator = new eZIntegerValidator();
    }

    function validateStringHTTPInput( $data, &$contentObjectAttribute, &$classAttribute )
    {
        if ( $contentObjectAttribute->attribute( 'data_int' ) == 1 )
        {
            return EZ_INPUT_VALIDATOR_STATE_ACCEPTED;
        }

        $maxLen = $classAttribute->attribute( EZ_DATATYPESTRING_MAX_LEN_FIELD );
        $textCodec =& eZTextCodec::instance( false );
        if ( $textCodec->strlen( $data ) > $maxLen and
             $maxLen > 0 )
        {
            $contentObjectAttribute->setValidationError( ezi18n( 'kernel/classes/datatypes',
                                                                 'The input text is too long. The maximum number of characters allowed is %1.' ),
                                                         $maxLen );
            return EZ_INPUT_VALIDATOR_STATE_INVALID;
        }

        if ( preg_match( '/^[a-z0-9-_]+$/', $data )  != 1 )
        {
            $contentObjectAttribute->setValidationError( ezi18n( 'kernel/classes/datatypes',
                                                                 'Invalid UNIX name. Allowed characters: a-z, 0-9, - and _' ) );
            return EZ_INPUT_VALIDATOR_STATE_INVALID;
        }

        include_once( 'lib/ezdb/classes/ezdb.php' );
        $db =& eZDB::instance();

        include_once( 'kernel/classes/ezcontentobjectversion.php' );
        //eZDebug::createAccumulator( 'unixname', false, 'unix name uniqueness check SQL' );
        //eZDebug::accumulatorStart( 'unixname' );
        $sql = 'SELECT DISTINCT a.contentobject_id FROM ezcontentobject_attribute a, ezcontentobject_version v  WHERE a.contentclassattribute_id=' . $classAttribute->attribute( 'id' ) . ' AND a.contentobject_id=v.contentobject_id AND a.version=v.version AND v.status IN (' . EZ_VERSION_STATUS_PUBLISHED . ',' . EZ_VERSION_STATUS_PENDING . ') and a.sort_key_string="' . $db->escapeString( $textCodec->convertString( $data ) ) . '"';

        $result = $db->arrayQuery( $sql );
        //eZDebug::accumulatorStop( 'unixname' );

        eZDebug::writeDebug( $result, 'UnixNameType::validateStringHTTPInput' );
        if ( count( $result ) > 0 )
        {
            $contentObjectAttribute->setValidationError( ezi18n( 'kernel/classes/datatypes',
                                                                 'This name is already in use. Choose another one.' ) );
            return EZ_INPUT_VALIDATOR_STATE_INVALID;
        }

        return EZ_INPUT_VALIDATOR_STATE_ACCEPTED;
    }

    function fetchObjectAttributeHTTPInput( &$http, $base, &$contentObjectAttribute )
    {
        // don't process if object is already published
        if ( $contentObjectAttribute->attribute( 'data_int' ) == 1 )
        {
            return true;
        }

        if ( $http->hasPostVariable( $base . '_ezstring_data_text_' . $contentObjectAttribute->attribute( 'id' ) ) )
        {
            $data = $http->postVariable( $base . '_ezstring_data_text_' . $contentObjectAttribute->attribute( 'id' ) );
            $contentObjectAttribute->setAttribute( 'data_text', $data );
            return true;
        }
        return false;
    }

    function onPublish( &$contentObjectAttribute, &$contentObject, &$publishedNodes )
    {
        // set a flag, so we know this attribute has content in the published version
        if ( $contentObjectAttribute->attribute( 'data_int' ) != 1 && $contentObjectAttribute->attribute( 'has_content' ) )
        {
            $contentObjectAttribute->setAttribute( 'data_int', 1 );
            $contentObjectAttribute->store();
        }
    }
}

eZDataType::register( EZ_DATATYPESTRING_UNIXNAME, "unixnametype" );

?>