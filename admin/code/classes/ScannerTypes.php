<?php

class ScannerTypes
{

    /**********************************************************************************************
     **************** S T A R T   S C A N N E R    T Y P E    D E F I N I T I O N S ***************
     **********************************************************************************************/

    /**
     * the default, GIMP digitally shadded, pixel-perfect documents
     */
    public const DEFAULT_SCANNER = 1;

    /**
     * documents scanned with our brand new fast-hp scanner, @generic mode - typically produces 2550*4200 res image
     */
    public const FAST_HP_SCANNER_GENERIC_MODE = 2;

    /**
     * documents scanned with our brand new fast-hp scanner, @300PPI - typically produces 2550*3300 res image
     */
    public const FAST_HP_SCANNER_300PPI = 3;

    /***********************************************************************************************
     ********************* E N D   S C A N N E R    T Y P E    D E F I N I T I O N S ***************
     ***********************************************************************************************/

    public static function getScannerDescription(int $scannertype): string
    {
        $refl = new \ReflectionClass("ScannerTypes");
        foreach ($refl->getConstants() as $name => $value) {
            if ($value == $scannertype) {
                $classConstant = new \ReflectionClassConstant("ScannerTypes", $name);
                $desc          = $classConstant->getDocComment();
                return $desc;
            }
        }

        return "No description found for scanner identified by id $scannertype";
    }
}
