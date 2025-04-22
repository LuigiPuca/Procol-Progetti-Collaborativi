<?php
/**
 * Definisce da quale metodo deve essere necessariamente formato un 
 * Singleton
 */

interface SingletonInterface {
    public static function getIstanza(...$args);
}

?>