<?php
Ant::import('protocols.a_protocol');

class Http extends AProtocol {
    /**
     * @see AProtocol::initial()
     */
    public function initial($receiveData, &$conn) {
        
    }

    /**
     * @see AProtocol::decode()
     */
    public function decode($data) {
        // TODO: Implement decode() method.
    }

    /**
     * @see AProtocol::encode()
     */
    public function encode($data) {
        // TODO: Implement encode() method.
    }
}