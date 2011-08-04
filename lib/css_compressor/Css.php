<?php
/*
 * CSS Compressor
 * Copyright: http://www.dscripts.net
 * Author: Burhan Uddin
 * Date: 2010-06-21
*/
    class Css {
        private $css_files = array();
        private $base_path = '';

        /*
         * Create css instance (Constructor)
         * access: public
         * @param $css_file: array() array of css files
         * @param $base_apth: (optional) string, base path to css files
        */
        public function  __construct($css_files, $base_path = '') 
        {
            $this->base_path = $base_path;
            $this->add($css_files);
        }

        /*
         * Adds css files to array for compression
         * @param $css_files: array() array of css files or single css file name	 
        */
        public function add($css_files) {
            // adds all css if array
            if(is_array($css_files)) {
                foreach($css_files as $css_file) {
                    $this->add($css_file);
                }
            }
            else {
                if(file_exists($this->base_path.'/'.$css_files))
                    $this->css_files[] = $this->base_path.'/'.$css_files;
            }

        }

        /*
         * Performs css compression and set output
         * access: public
         * @param $gz: (optional) boolean, if true compress output in gz format
        */
        public function output($file_name) 
        {
            $fd = fopen($file_name, "w"); 
            foreach($this->css_files as $css_file)
            {
                $buffer = file_get_contents ($css_file);
                // remove comments
                $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);
                // remove tabs, spaces, newlines, etc.
                $buffer = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $buffer);
                
                fwrite ($fd, $buffer);
            }
            fclose($fd);
        }

        /*
         * creates an md5 key for compressed css file
         * that can be used for file name when cached
         * retrun string
         */
        public function create_key() {
            $key = implode('', $this->css_files);
            return md5($key);
        }

    }
?>