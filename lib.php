Примеры из Zend Framework 1.11

    /**
     * Выполнение запросов к серверу memcached
     * @param string $command
     * @return string
     * @throws Exception
     */
    function getDataFromMemcached($command)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === FALSE)
            throw new Exception(socket_strerror(socket_last_error()));
        if (socket_connect($socket, '127.0.0.1', 11211) === FALSE)
            throw new Exception(socket_strerror(socket_last_error()));

        if (socket_write($socket, "$command\n") === FALSE)
            throw new Exception(socket_strerror(socket_last_error()));
        $answer = '';
        while ($out = socket_read($socket, 1024, PHP_BINARY_READ)) {
            $answer .= $out;
            if (strlen($out) < 1024)
                break;
        }
        socket_close($socket);
        return $answer;
    }

    /**
     * Получение статистики memcached
     * @throws Exception
     * @return void
     */
    public function memcachedstatAction()
    {
        try {
            $answer = $this->_getDataFromMemcached('stats');
            $response = array();
            foreach (explode("\r\n", $answer) as $row) {
                $data = explode(' ', $row);
                if (count($data) != 3)
                    continue;
                list($stat, $key, $value) = $data;
                $response[] = array(
                    'key'   => $key,
                    'value' => $value
                );
            }
        } catch (Exception $ex) {
            $this->getResponse()
            ->setHeader('Content-Type', 'application/json; charset=UTF-8')
            ->appendBody(Zend_Json::encode(array('total' => 1, 'items' => array(
                'key'   => 'ошибка',
                'value' => $ex->getMessage())))
            );
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json; charset=UTF-8')
            ->appendBody(Zend_Json::encode(array('total' => 1, 'items' => $response)));
    }

    /**
     * Получение ключей
     * @throws Exception
     * @return void
     */
    public function memcachedkeysAction()
    {
        try {
            $answer = $this->_getDataFromMemcached('stats items');
            $slabs = $response = array();
            foreach (explode("\r\n", $answer) as $row) {
                $data = explode(':', $row);
                if (count($data) != 3)
                    continue;
                list($stat, $slab, $value) = $data;
                if (! in_array($slab, $slabs)) {
                    $slabs[] = $slab;
                    $items = $this->_getDataFromMemcached("stats cachedump {$slab} 0");
                    foreach( explode("\r\n", $items) as $item) {
                        if (preg_match('/^ITEM\s([^\s]+)\s\[(\d{1,4})\sb;\s(\d{10})\ss\]$/', $item, $matches)) {
                            $key = $matches[1];
                            $size = $matches[2];
                            $time = date('Y-m-d H:i:s', $matches[3]);
                            $value = $this->_getDataFromMemcached("get {$key}");
                            $valueObj = explode("\r\n", $value);
                            $value = (count($valueObj) == 4) ? $valueObj[1] : '?';
                            $response[] = array(
                                'key'   => $key,
                                'size'  => $size,
                                'time'  => $time,
                                'value' => $value
                            );
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            $this->getResponse()
            ->setHeader('Content-Type', 'application/json; charset=UTF-8')
            ->appendBody(Zend_Json::encode(array('total' => 1, 'items' => array(
                'key'   => 'ошибка',
                'value' => $ex->getMessage())))
            );
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json; charset=UTF-8')
            ->appendBody(Zend_Json::encode(array('total' => 1, 'items' => $response)));
    }