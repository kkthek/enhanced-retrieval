<?php
namespace DIQA\FacetedSearch;

/*
 * Copyright (C) Vulcan Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.If not, see <http://www.gnu.org/licenses/>.
 *
 */
use Exception;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * @file
 * @ingroup FacetedSearch
 *
 * This file contains the class FSSolrIndexer. It encapsulates access to the
 * Apache Solr indexing service.
 *
 * @author Thomas Schweitzer
 * Date: 22.02.2011
 *
 */
if ( !defined( 'MEDIAWIKI' ) ) {
    die( "This file is part of the Enhanced Retrieval Extension extension. It is not a valid entry point.\n" );
}

/**
 * This class offers methods for accessing an Apache Solr indexing server.
 *
 * @author thsc
 *
 */
abstract class FSSolrIndexer implements FSIndexerInterface {

    //--- Constants ---
    const PING_CMD = 'solr/<CORE>/admin/ping';
    const CREATE_FULL_INDEX_CMD = 'solr/<CORE>/dataimport?command=full-import';
    const FULL_INDEX_CLEAN_OPT = '&clean=true';
    const COMMIT_UPDATE_CMD = 'solr/<CORE>/update?commit=true';
    const EXTRACT_CMD = 'solr/<CORE>/update/extract?extractOnly=true&wt=json';
    const DELETE_INDEX_QUERY = '<delete><query>*:*</query></delete>';
    const DELETE_DOCUMENT_BY_ID = '<delete><id>$1</id></delete>'; // $1 must be replaced by the actual ID
    const QUERY_PREFIX = 'solr/<CORE>/select/?';

    const HTTP_OK = 200;

    //--- Private fields ---

    // string: Name or IP address of the host of the server
    private $mHost;

    // int: Server port of the Solr server
    private $mPort;

    // string: Base URL for all HTTP request to the SOLR server
    private $mBaseURL;

    // string: Base64-encoded user:pass
    private $authBase64;

    // string: name of SOLR core
    private $indexCore;

    //--- getter/setter ---
    public function getHost()    { return $this->mHost; }
    public function getPort()    { return $this->mPort; }

    //--- Public methods ---

    /**
     * Creates a new Solr indexer object. This method can only be called from
     * derived classes.
     *
     * @param string $host
     *         Name or IP address of the host of the server
     * @param int $port
     *         Server port of the Solr server
     * @param string $user
     *         Username for Basic Auth
     * @param string $pass
     *         Password for Basic Auth
     * @param string $indexCore
     *         Name of SOLR core
     */
    protected function __construct($host, $port, $user, $pass, $indexCore) {
        $this->mHost = $host;
        $this->mPort = $port;
        $this->mBaseURL = "http://$host:$port/";
        $this->authBase64 = base64_encode("$user:$pass");
        $this->indexCore = $indexCore;
    }


    /**
     * Pings the server of the indexer and checks if it is responding.
     * @return bool
     *     <true>, if the server is responding
     *     <false> otherwise
     */
    public function ping() {
        $result = $this->sendCommand(self::PING_CMD, $resultCode);
        return $resultCode == self::HTTP_OK;
    }

    /**
     * Creates a full index of all available semantic data.
     *
     * @param bool $clean
     *         If <true> (default), the existing index is cleaned before the new
     *         index is created.
     * @return bool success
     *         <true> if the operation was successful
     *         <false> otherwise
     */
    public function createFullIndex($clean = true) {
        $cmd = self::CREATE_FULL_INDEX_CMD;
        if ($clean) {
            $cmd .= self::FULL_INDEX_CLEAN_OPT;
        }
        $result = $this->sendCommand($cmd, $resultCode);
        return $resultCode == self::HTTP_OK;
    }

    /**
     * Deletes the complete index.
     */
    public function deleteIndex() {
        $rc = $this->postCommand(self::COMMIT_UPDATE_CMD, self::DELETE_INDEX_QUERY);
        return $rc == self::HTTP_OK;
    }

    /**
     * Sends a raw query to the SOLR server in the query format expected by SOLR.
     * @param string $query
     *         Raw query string (without base URL)
     * @return mixed bool/string
     *         Result of the query or <false> if request failed.
     */
    public function sendRawQuery($query) {
        $result = $this->sendCommand(self::QUERY_PREFIX.$query, $resultCode);
        return ($resultCode == self::HTTP_OK) ? $result : false;
    }

    /**
     * Updates the index on the SOLR server for the given document.
     *
     * The given document specification is transformed to XML and then sent to
     * the SOLR server.
     *
     * @param array $document
     *         This array contains key-value pairs. The key is a field of the
     *         SOLR document. The value may be a single string i.e. the value of
     *         the SOLR field or an array of string if the field is multi-valued.
     * @param array $options
     * @param bool $debugMode prints verbose output
     *
     * @return bool
     *         <true> if the update was sent successfully
     */
    public function updateIndex(array $document, array $options, bool $debugMode = false) {
        // Create the XML for the document
        $xml = "<add>\n\t<doc>\n";

        // this is a dummy boost for the sole purpose of being requested even if no filter is applied.
        // it always has the value "1". it assures that boosting is actually effective.
        global $fsgActivateBoosting;
        if ( isset($fsgActivateBoosting) && $fsgActivateBoosting === true ) {
            $boost = $options['smwh_boost_dummy']['boost'];
            $xml .= "\t\t<field name='smwh_boost_dummy'><![CDATA[" . $boost . "]]></field>\n";
        }

        foreach ($document as $field => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $xml .= "\t\t<field name='$field' %$field:options%><![CDATA[$v]]></field>\n";
                }
            } else {
                $xml .= "\t\t<field name='$field' %$field:options%><![CDATA[$value]]></field>\n";
            }

            // assemble xml attributes for field
            $optionAtts = '';
            if (isset($options[$field])) {
                foreach($options[$field] as $name => $option) {
                    $optionAtts .= "$name='$option' ";
                }
            }
            if (isset($options['*']) && !array_key_exists($field, $options)) {
                foreach($options['*'] as $name => $option) {
                    $optionAtts .= "$name='$option' ";
                }
            }

            if (isset($options[$field]['boost']) && $options[$field]['boost'] === false) {
                $optionAtts = '';
            }

            // add them
            $xml = str_replace("%$field:options%", $optionAtts, $xml);
        }

        $xml .= "\t</doc>\n</add>";

        // Send the XML as update command to the SOLR server
        $rc = $this->postCommand(self::COMMIT_UPDATE_CMD, $xml);

        if ($debugMode) {
            print "$xml\n";
        }

        return $rc == self::HTTP_OK;
    }

    /**
     * Sends a document to Tika and extracts text. If Tika does not
     * know the format, an empty string is returned.
     *
     *  - PDF
     *  - DOC/X (Microsoft Word)
     *  - PPT/X (Microsoft Powerpoint)
     *  - XLS/X (Microsoft Excel)
     *
     * @param mixed $title Title or filepath
     *         Title object of document (must be of type NS_FILE)
     *         or a filepath in the filesystem
     * @return array e.g. [ text => extracted text of document, xml => full XML-response of Tika ]
     * @throws Exception
     */
    public function extractDocument($title) {
        if ($title instanceof Title) {
            $file = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile($title);
            $filepath = $file->getLocalRefPath();
        } else {
            $filepath = $title;
        }

        // get file and extension
        $ext = pathinfo($filepath, PATHINFO_EXTENSION);

        // choose content type
        if ($ext == 'pdf') {
            $contentType = 'application/pdf';
        } else if ($ext == 'doc' || $ext == 'docx') {
            $contentType = 'application/msword';
        } else if ($ext == 'ppt' || $ext == 'pptx') {
            $contentType = 'application/vnd.ms-powerpoint';
        } else if ($ext == 'xls' || $ext == 'xlsx') {
            $contentType = 'application/vnd.ms-excel';
        } else {
            // general binary data as fallback (don't know if Tika accepts it)
            $contentType = 'application/octet-stream';
        }

        // do not index unknown formats
        if ($contentType == 'application/octet-stream') {
            return [];
        }

        // send document to Tika and extract text
        if ($filepath == '') {
            if ( PHP_SAPI === 'cli' && !defined('UNITTEST_MODE')) {
                throw new Exception(sprintf("\nWARN  Empty file path for '%s'. Can not index document properly.\n", $title->getPrefixedText()));
            }
            return [];
        }

        $result = $this->postCommandReturn(self::EXTRACT_CMD, file_get_contents($filepath), $contentType, $rc);

        if ($rc != 200) {
            throw new Exception(sprintf("\nERROR Keine Extraktion möglich: %s (HTTP code: %s) %s\n", 
                    $title->getPrefixedText(), $rc, $result));
        }

        // SOLR-4 uses XML as default
        // $xml = simplexml_load_string($result);
        // if (!isset($xml->str)) {
        //     throw new Exception(sprintf('Keine Extraktion möglich: %s', $title->getPrefixedText()));
        // }
        // $text = $xml->str;
        // // strip tags and line feeds
        // $text = str_replace(array("\n","\t"), ' ', strip_tags(str_replace('<', ' <', $text)));

        $obj = json_decode($result);
        $xml = $obj->{''};
        $text = strip_tags( str_replace('<', ' <', $xml) );
        // TODO actually we should do sth. like this
        // $text = $xml->xpath( '//body/text()' )
        // but this does not work for my sample document, possibly due to the SimpleXML of PHP and XML that TIKA creates

        $text = preg_replace('/\s\s*/', ' ', $text );

        if ($text == '') {
            throw new Exception(sprintf("\nWARN Kein extrahierter Text gefunden: %s\n", $title->getPrefixedText()));
        }

        return [ 'xml' => $xml, 'text' => $text ];
    }

    /**
     * Copies an indexed document with the ID $sourceID to a new document with the
     * new ID $targetID.
     *
     * @param int/string $sourceID
     *         The document ID of the source document
     * @param int/string $targetID
     *         The target ID of the copied document.
     * @param array(string => string) $ignoreCopyFields
     *         Some fields are copied automatically by the <copyfield> command in
     *         schema.xml. If a regular expression in a key of this array matches a
     *         field then the corresponding field that matches the value is removed.
     *         Example: "^(.*?)_t$" => "$1_s"
     *                 This matches the field "someText_t", thus the field "someText_s"
     *                 is removed. This corresponds to the copyfield command:
     *                 <copyField source="*_t" dest="*_s"/>
     * @return bool
     *         <true> if the copy was created successfully
     *         <false> otherwise
     * @deprecated
     */
    public function copyDocument($sourceID, $targetID, $ignoreCopyFields = null) {
        $doc = array();

        // Get the document with the old id
        $r = $this->sendRawQuery("q=id:$sourceID&wt=json");
        if ($r === false) {
            return false;
        }

        $doc = json_decode($r, true);
        $doc = @$doc['response']['docs'][0];

        if (!isset($doc)) {
            // wrong structure of the document
            return false;
        }
        // Set the new ID
        $doc['id'] = $targetID;

        if (!is_null($ignoreCopyFields)) {
            foreach ($ignoreCopyFields as $srcPattern => $targetPattern) {
                foreach ($doc as $field => $val) {
                    $f = preg_replace("/$srcPattern/", $targetPattern, $field);
                    if ($f !== $field) {
                        // The source pattern matched => remove the target field
                        unset($doc[$f]);
                    }
                }
            }
        }
        // create dummy options
        $options = [] ;
        $options['smwh_boost_dummy'] = [];
        $options['smwh_boost_dummy']['boost'] = 1;

        // Create the copy
        return $this->updateIndex($doc, $options);
    }

    /**
     * Deletes the document with the ID $id from the index.
     *
     * @param string/int $id  ID of the document to delete.
     * @return bool
     *         <true> if the document was deleted successfully
     *         <false> otherwise
     *
     */
    public function deleteDocument($id) {
        $cmd = str_replace('$1', "$id", self::DELETE_DOCUMENT_BY_ID);
        $rc = $this->postCommand(self::COMMIT_UPDATE_CMD, $cmd);
        return $rc == self::HTTP_OK;
    }

    //--- Private methods ---

    /**
     * Sends a command via curl to the SOLR server.
     * @param string $command
     *         The command is appended to the base URI of the server and then sent
     *         as HTTP request.
     * @param int $resultCode
     *         Returns the status of the HTTP request
     *             200 - HTTP_OK
     * @return string
     *         Result of the request
     */
    private function sendCommand($command, &$resultCode) {
        $url = $this->createCommandUrl($command);

        $curl = curl_init( $url );
        if( defined( 'ERDEBUG' ) ) {
            curl_setopt( $curl, CURLOPT_VERBOSE, 1 );
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: Basic {$this->authBase64}"] );

        ob_start();
        $ok = curl_exec( $curl );
        $result = ob_get_contents();
        if ($ok === false) {
            $result = curl_error($curl);
        }
        ob_end_clean();

        $info = curl_getinfo( $curl );

        curl_close( $curl );

        $resultCode = $info['http_code'];
        return $result;
    }

    /**
     * Sends a POST command to the SOLR server
     * @param string $command
     *         The command is appended to the base URI of the server and then sent
     *         as HTTP request.
     * @param string $data
     *         The data that will be posted.
     * @param int $resultCode
     *         Returns the status of the HTTP request
     *             200 - HTTP_OK
     */
    private function postCommand($command, $data) {
        $url = $this->createCommandUrl($command);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: text/xml',
                "Authorization: Basic {$this->authBase64}"] );
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_POST, 1);

        $result = curl_exec($curl);
        if ($result === false) {
            throw new Exception("\nERROR CURL error " . curl_error($curl) . "\n", 500);
        }
        $info = curl_getinfo($curl);
        curl_close($curl);

        $HTTPCode = $info['http_code'];
        if ($HTTPCode != 200) {
            throw new Exception("\nERROR HTTP error $HTTPCode for command $command.\nURL=$url\nERROR Payload:\n$data\nERROR HTTP response:\n$result", $HTTPCode);
        }
        return $HTTPCode;
    }

    /**
     * Sends a POST command to the SOLR server and returns result.
     *
     * @param string $command
     *         The command is appended to the base URI of the server and then sent
     *         as HTTP request.
     * @param string $data
     *         The data that will be posted.
     * @param string $contentType
     *         The content type of the data.
     * @param int $resultCode
     *         The HTTP result code
     * @return String
     *         Returns the result of the request
     *
     */
    private function postCommandReturn($command, $data, $contentType, &$resultCode) {
        $url = $this->createCommandUrl($command);

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
                "Content-Type: $contentType",
                "Authorization: Basic {$this->authBase64}"] );
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_POST, 1);

        $result = curl_exec($curl);
        if ($result === false) {
            $result = curl_error($curl);
        }
        $info = curl_getinfo($curl);
        curl_close($curl);

        $resultCode = $info['http_code'];
        return $result;
    }

    /**
     * @param  String $command
     * @return String the URL for the provided command as a string, including the core
     */
    private function createCommandUrl($command){
        if($this->indexCore == '') {
            $url = $this->mBaseURL . str_replace('<CORE>/', '', $command);
        } else {
            $url = $this->mBaseURL . str_replace('<CORE>/', $this->indexCore . '/', $command);
        }
        return $url;
    }
}
