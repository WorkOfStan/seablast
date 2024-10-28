<?php

namespace Seablast\Seablast\Tracy;

use Tracy\IBarPanel;

/**
 * Tracy panel showing info about Template
 */
class BarPanelTemplate implements IBarPanel
{
    use \Nette\SmartObject;

    /** @var bool false = info, true = error */
    protected $errorPanel = false;
    /** @var array<mixed> */
    protected $panelDetails;
    /** @var string */
    protected $tabTitle;

    /**
     * @param string $tabTitle
     * @param array<mixed> $panelDetails
     */
    public function __construct(string $tabTitle, array $panelDetails)
    {
        $this->tabTitle = $tabTitle;
        $this->panelDetails = $panelDetails;
    }

    /**
     * Renders HTML code for custom tab.
     *
     * @return string
     */
    public function getTab(): string
    {
        $style = $this->errorPanel ?
            'display: block;background: #D51616;color: white;font-weight: bold;margin: -1px -.4em;padding: 1px .4em;' :
            '';
        $icon = ''; // Placeholder for icon implementation<img src="data:image/png;base64,<zakodovany obrazek>" />
        $label = '<span class="tracy-label" style="' . $style . '">' . $this->tabTitle . '</span>';
        return $icon . $label;
    }

    /**
     * Renders HTML code for custom panel.
     *
     * @return string
     */
    public function getPanel(): string
    {
        $cntTable = '';

        foreach ($this->panelDetails as $id => $detail) {
            $cntTable .= '<tr><td>' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '</td><td>';
        
            if (is_array($detail)) {
                $cntTable .= '<table>';
                foreach ($detail as $k => $v) {
                    $valueStr = htmlspecialchars(print_r($v, true), ENT_QUOTES, 'UTF-8');
                    $cntTable .= '<tr><td>' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '</td><td title="'
                        . $valueStr . '">' . mb_substr($valueStr, 0, 240, 'UTF-8') . '</td></tr>';
                }
                $cntTable .= '</table>';
            } else {
                $cntTable .= htmlspecialchars(print_r($detail, true), ENT_QUOTES, 'UTF-8');
            }
        
            $cntTable .= '</td></tr>';
        }
        return
            // title
            '<h1>' . htmlspecialchars($this->tabTitle, ENT_QUOTES, 'UTF-8') . '</h1>'
            // content
            . '<div class="tracy-inner tracy-InfoPanel"><table><tbody>'
            . $cntTable
            . '</tbody></table>* Hover over field to see its full content.</div>';
    }

    /**
     * Set panel to be displayed as error.
     * If to be set to info again, call setError(false)
     *
     * @param bool $error OPTIONAL
     * @return void
     */
    public function setError(bool $error = true): void
    {
        $this->errorPanel = (bool) $error;
    }
}
