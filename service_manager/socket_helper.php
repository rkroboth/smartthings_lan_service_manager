<?php

class socket_helper {

    // reads from the socket until there's nothing else coming in
    public static function read_http_request($socket, $timeout = 2){

        // get request line
        $result = self::read_line_from_socket($socket);
        if (!$result['success']){
            return $result;
        }
        $request_line = $result['line'];

        if (preg_match('#^(GET||POST)\s+([^\s]+)\s(.*)$#', $request_line, $matches)){
            $method = $matches[1];
            $url = $matches[2];
            $http_version = $matches[3];
        } else {
            socket_close($socket);
            return array(
                "success" => false,
                "error_msg" => "Malformed request line"
            );
        }


        // headers
        $headers = array();
        while (1){
            $result = self::read_line_from_socket($socket);
            if (!$result['success']){
                return $result;
            }
            $header_line = $result['line'];

            // blank line denotes end of headers section
            if ($header_line == ""){
                break;
            }

            $ary = explode(":", $header_line);
            $key = trim($ary[0]);
            $value = "";
            if (isset($ary[1])){
                $value = trim($ary[1]);
            }
            $headers[$key] = $value;
        }

        // if one of the headers was a content-length (like from a POST),
        // then expect to read that content now
        $content = "";
        $content_length = 0;
        foreach ($headers as $h => $v){
            if (strtolower($h) == "content-length"){
                $content_length = $v;
                break;
            }
        }
        if ($content_length){
            $result = self::read_line_from_socket($socket, $content_length, null, false);
            if (!$result['success']){
                return $result;
            }
            $content = $result['line'];
        }

        return array(
            "success" => true,
            "request" => array(
                "method" => $method,
                "url" => $url,
                "http_version" => $http_version,
                "headers" => $headers,
                "content" => $content,
            )
        );

    }

    public static function send_http_response($socket, $response = "200 OK", $data = null, $content_type = "text/html"){
        self::write_line_to_socket($socket, "HTTP/1.1 " . $response);
        self::write_line_to_socket($socket, "Content-Type: " . $content_type . "; charset=utf-8");
        if ($data != "" && $data !== null){
            self::write_line_to_socket($socket, "Content-Length: " . strlen($data));
        }
        self::write_line_to_socket($socket, "Connection: close");
        self::write_line_to_socket($socket, "");
        if ($data != "" && $data !== null){
            self::write_data_to_socket($socket, $data);
        }
        socket_close($socket);
    }

    /**
     * Writes a string to the socket followed by a "\r\n"
     * @param resource $socket
     * @param string $line
     * @return array A hashed array with a "success" element of a bool indicating the success of the call,
     *               and a "error_msg" element of a string containing the error message if it failed
     */
    public static function write_line_to_socket($socket, $line){
        $line .= "\r\n";
        return self::write_data_to_socket($socket, $line);
    }

    /**
     * Writes a string of data to the socket
     * @param resource $socket
     * @param string $data
     * @return array A hashed array with a "success" element of a bool indicating the success of the call,
     *               and a "error_msg" element of a string containing the error message if it failed
     */
    public static function write_data_to_socket($socket, $data){
        $bytes_written = socket_write($socket, $data);
        if ($bytes_written === false){
            return array(
                "success" => false,
                "error_msg" => "socket_write() failed. Reason: " . socket_strerror(socket_last_error($socket))
            );
        }
        if ($bytes_written != strlen($data)){
            return array(
                "success" => false,
                "error_msg" => "socket_write() did not write the expected number of bytes (" . $bytes_written . ")"
            );
        }

        return array(
            "success" => true,
        );

    }

    /**
     * Read data from the socket either until it encounters one of these conditions:
     *   - $bytes_to_read has been read
     *   - the character sequence \r\n has been encountered
     *   - no more data is coming in,
     * then returns that string.
     *
     * In the case of the \r\n is encountered, that \r\n is may or may not be trimmed off the end of the string,
     * this is controlled by the $trim param.
     *
     * Returns a failure message if the socket runs out of data before either the bytes_to_read
     * or the \r\n is encountered.
     *
     * @param resource $socket
     * @param int $bytes_to_read Limit of bytes to read, or null if no limit
     * @param int $timeout Timeout in seconds, or null to use default
     * @param bool $trim Whether to trim any \r\n off the end of the string before returning it
     * @return array A hashed array with a "success" element of a bool indicating the success of the call,
     *               and a "line" element of a string containing the line read,
     *               or a "error_msg" element of a string containing the error message if it failed
     */
    public static function read_line_from_socket($socket, $bytes_to_read = null, $timeout = null, $trim = true){

        if ($timeout === null){
            $timeout = 2;
        }
        $sockets = array($socket);
        $line = "";
        $return_found = false;
        while (1){
            $write = null;
            $except = null;
            $num_sockets_modified = socket_select($sockets, $write, $except, $timeout);
            if (!$num_sockets_modified || count($sockets) == 0){
                // no more data coming in, so just return what we have
                if ($trim){
                    $line = trim($line);
                }
                return array(
                    "success" => true,
                    "line" => $line
                );
            }
            $data = @socket_read($socket, 1, PHP_BINARY_READ);

            // connection was dropped
            if ($data === false) {
                return array(
                    "success" => false,
                    "error_msg" => "Connection has been dropped"
                );
            }

            $line .= $data;

            if ($bytes_to_read !== null){
                if (strlen($line) == $bytes_to_read){
                    if ($trim){
                        $line = trim($line);
                    }
                    return array(
                        "success" => true,
                        "line" => $line,
                    );
                }
            }

            if ($data == "\r"){
                $return_found = true;
            }
            else if ($return_found && $data == "\n"){
                if ($trim){
                    $line = trim($line);
                }
                return array(
                    "success" => true,
                    "line" => $line,
                );
            }
            else {
                $return_found = false;
            }

        }

    }


} 