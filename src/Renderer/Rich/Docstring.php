<?php

class Kint_Renderer_Rich_Docstring extends Kint_Renderer_Rich_Plugin
{
    public function render($r)
    {
        if (!($r instanceof Kint_Object_Representation_Docstring)) {
            return false;
        }

        $docstring = array();
        foreach (explode("\n", $r->contents) as $line) {
            $docstring[] = trim($line);
        }

        $docstring = implode("\n", $docstring);

        $location = array();

        if ($r->class) {
            $location[] = 'Inherited from '.Kint_Object_Blob::escape($r->class);
        }
        if ($r->file && $r->line) {
            $location[] = 'Defined in '.Kint_Object_Blob::escape(Kint::shortenPath($r->file)).':'.((int) $r->line);
        }

        if ($location) {
            if (strlen($docstring)) {
                $docstring .= "\n\n";
            }

            $location = '<small>'.implode("\n", $location).'</small>';
        } elseif (strlen($docstring) === 0) {
            return '';
        }

        return '<pre>'.Kint_Object_Blob::escape($docstring).$location.'</pre>';
    }
}
