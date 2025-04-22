<?php

require_once __DIR__ . "/../interfaces/SingletonInterface.php";

/**
 * Definisce da quali altri metodi deve essere formato il Singleton che, per
 * essere istanziato, deve essere ereditato.
 */



abstract class Singleton implements SingletonInterface {
    # [] e non null perchè ogni singleton ereditabile ha la sua istanza massima
    protected static array $istanze = []; 

    # costruttore necessario per istanziare la sottoclasse
    protected function __construct() {}

    # impedisce clonazione e deserializzazione
    protected function __clone() {}
    public function __wakeup() {
        throw new Exception("Errore: Impossibile deserializzare il Singleton");
    }

    public static function getIstanza(...$args) {
        /** 
         * in caso di eredità non si usa self() ma static(). 
         * tuttavia poichè i parametri inseriti nel costruttore della 
         * sottoclasse non coincindono con quelle definiti nella classe
         * astratta, si preferisce istanziare una ReflectionClass con
         * gli argomenti necessari.
         * 
         */ 
        $classe_associata = static::class;

        if (!isset(self::$istanze[$classe_associata])) {
            # con reflection  vengono passati parametri al costurttore
            $riflessione = new ReflectionClass($classe_associata);
            # nel caso le sottoclassi hanno costruttore protected/private
            $costruttore = $riflessione->getConstructor();

            # si rende il costruttore della sottoclasse accessibile a reflected
            if ($costruttore !== null && !$costruttore->isPublic()) {
            # non pubblico : crea oggetto senza chiamare il costruttore
                $istanza = $riflessione->newInstanceWithoutConstructor();
    
                #rende accessibile il costruttore e invocalo manualmente
                $costruttore->setAccessible(true);
                $costruttore->invokeArgs($istanza, $args);
                self::$istanze[$classe_associata] = $istanza;
            } else {
                # costruttore pubblico: usa direttamente newInstanceArgs
                self::$istanze[$classe_associata] = 
                    $riflessione->newInstanceArgs($args);
            }
        }
        return self::$istanze[$classe_associata];
    }

}
?>