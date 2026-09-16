<?php

namespace App\Mcp;

use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Laravel\Prompts\Themes\Default\SelectPromptRenderer;

/**
 * The engine's prompt theme: the default one, in the PanelAlpha orange.
 *
 * Two hooks, and between them they carry the whole look:
 *
 *  - `box()` paints the frame through a method named by the `$color` it is
 *    handed, so repainting the default gray is a method of this class.
 *  - `cyan()` is the renderer's accent - the title, both markers of the
 *    highlighted row and the scrollbar handle. Nothing in a select prompt is
 *    cyan for any other reason, so one override moves them together.
 *
 * The unselected row is left as the default renderer drew it: its marker and
 * label are *dim*, and that is what says "not the one you are on". Colouring
 * them too would make every row look selected. The hint stays gray, and the
 * states that name a colour of their own - red for a cancelled prompt, yellow
 * for one that failed - are passed through untouched.
 */
class PanelAlphaTheme extends SelectPromptRenderer
{
    /** The name it is registered under; `default` is the one name that cannot be taken. */
    public const NAME = 'panelalpha';

    /**
     * 256-colour orange - 215, the same one the installer's outro paints
     * `pae connect` with (scripts/installer.sh, `outro_c 215`).
     *
     * Written as a code rather than picked by capability because the trait this
     * extends only knows the 16 named colours, and orange among them does not
     * exist: Symfony's hex path would degrade `#ffa500` to plain yellow on any
     * terminal that does not advertise `COLORTERM=truecolor`, which is most of
     * them. 215 is drawn as orange wherever 256 colours are supported at all.
     */
    private const ORANGE = "\e[38;5;215m";

    /**
     * Register the theme and make it active.
     *
     * Must run before a prompt is constructed: the renderer is resolved in the
     * prompt's constructor, from the exact class being built, so a theme added
     * afterwards changes nothing about a prompt that already exists.
     */
    public static function register(): void
    {
        Prompt::addTheme(self::NAME, [SelectPrompt::class => self::class]);
        Prompt::theme(self::NAME);
    }

    /**
     * Frame the box in orange wherever the default renderer would use gray.
     *
     * The default and submit states ask for gray, which is only a default - no
     * meaning is lost by repainting it. Cancel asks for red and error for
     * yellow, and those say something, so they are passed through untouched.
     */
    protected function box(
        string $title,
        string $body,
        string $footer = '',
        string $color = 'gray',
        string $info = '',
    ): self {
        return parent::box($title, $body, $footer, $color === 'gray' ? 'orange' : $color, $info);
    }

    /**
     * The accent: the label, the `› ●` of the highlighted row, the scrollbar
     * handle. Same colour as the frame, so the box reads as one piece.
     */
    public function cyan(string $text): string
    {
        return $this->orange($text);
    }

    /** The method `box()` looks for when it has been asked for an orange frame. */
    public function orange(string $text): string
    {
        return self::ORANGE . $text . "\e[39m";
    }
}
