<?php

namespace UserDOMP\WpAdminDS;

/**
 * Components — PHP helpers for the WP Admin Design System.
 *
 * All methods return HTML strings. Echo them directly or collect them.
 * User-supplied data is escaped via esc_html() / esc_attr() / esc_url().
 *
 * ─────────────────────────────────────────────────────────────────
 * QUICK REFERENCE
 * ─────────────────────────────────────────────────────────────────
 *
 *   Components::button('Save', 'primary')
 *   Components::button('Delete', 'danger', ['size' => 'sm', 'attrs' => ['id' => 'del-btn']])
 *
 *   Components::notice('Saved!', 'success')
 *   Components::notice('Failed', 'danger', ['title' => 'Error', 'dismissible' => true])
 *
 *   Components::badge('Pro', 'solid-accent')
 *   Components::badge('Active', 'dot-success')
 *
 *   Components::card(['title' => 'Settings', 'body' => $html])
 *   Components::card(['title' => 'Info', 'body' => $html, 'footer' => Components::button('OK')])
 *
 *   Components::input('api_token', ['placeholder' => 'Paste token', 'type' => 'password'])
 *   Components::select('office_id', ['1' => 'Casa Matriz', '2' => 'Sucursal Norte'], ['selected' => '1'])
 *   Components::textarea('notes', $value, ['rows' => 4])
 *   Components::checkbox('enable_sync', 'Enable stock sync', ['checked' => true, 'hint' => 'Runs every hour'])
 *   Components::toggle('sandbox_mode', 'Enable sandbox', ['checked' => false])
 *
 *   Components::form_group('API Token', Components::input('api_token'), ['hint' => 'Found in Bsale settings', 'required' => true])
 *
 *   Components::settings_section([
 *       'title' => 'API Connection',
 *       'desc'  => 'Credentials for Bsale.',
 *       'rows'  => [
 *           ['label' => 'Token',   'control' => Components::input('token')],
 *           ['label' => 'Sandbox', 'desc' => 'Use dev API', 'control' => Components::toggle('sandbox', 'Enable')],
 *       ],
 *       'footer' => Components::button('Save Changes'),
 *   ])
 *
 *   Components::page_header('DTE Settings', ['desc' => 'Configure Bsale integration', 'badge' => 'Pro'])
 *
 *   Components::stat('Documents Issued', '1,284', ['change' => '+12%', 'trend' => 'up'])
 *   Components::progress(75)
 *   Components::progress(3, 50, ['variant' => 'danger', 'label' => 'Failed'])
 *   Components::spinner()
 *   Components::empty_state('No documents yet', ['desc' => 'Complete an order to generate a DTE.'])
 *   Components::breadcrumbs([['label' => 'Settings', 'url' => '#'], ['label' => 'API']])
 *   Components::callout('Important', 'warning', 'Check your token before going live.')
 *   Components::kv_list(['Order ID' => '#1055', 'Folio' => '3421', 'Status' => 'Issued'])
 *   Components::chip('webhook', 'accent')
 *   Components::chip('failed', 'danger', ['removable' => true])
 *
 * ─────────────────────────────────────────────────────────────────
 */
class Components
{
    // ─────────────────────────────────────────────────────────────────
    // BUTTON
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a button.
     *
     * @param string $label    Button text (will be escaped).
     * @param string $variant  primary | secondary | ghost | danger
     * @param array  $options {
     *   @type string   $size     sm | lg (default: regular)
     *   @type string   $type     button | submit | reset (default: button)
     *   @type bool     $disabled
     *   @type bool     $full     Full-width button
     *   @type string   $href     Render as <a> instead of <button>
     *   @type array    $attrs    Extra HTML attributes ['id' => 'x', 'data-foo' => 'bar']
     * }
     */
    public static function button(string $label, string $variant = 'primary', array $options = []): string
    {
        $classes = ['wads-btn', 'wads-btn--' . $variant];

        if (!empty($options['size']))     $classes[] = 'wads-btn--' . $options['size'];
        if (!empty($options['full']))     $classes[] = 'wads-btn--full';

        $attrs = $options['attrs'] ?? [];

        if (!empty($options['href'])) {
            $attrs['href'] = esc_url($options['href']);
            $tag = 'a';
        } else {
            $tag  = 'button';
            $attrs['type'] = $options['type'] ?? 'button';
        }

        if (!empty($options['disabled'])) {
            $attrs['disabled'] = 'disabled';
        }

        $attr_str = self::build_attrs($attrs);
        $class_str = esc_attr(implode(' ', $classes));

        return sprintf(
            '<%1$s class="%2$s"%3$s>%4$s</%1$s>',
            $tag,
            $class_str,
            $attr_str ? ' ' . $attr_str : '',
            esc_html($label)
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // NOTICE
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a notice/alert.
     *
     * @param string $message  Notice body text (HTML allowed — not escaped internally).
     * @param string $type     success | warning | danger | error | info | neutral
     * @param array  $options {
     *   @type string $title       Optional bold title above the message.
     *   @type bool   $dismissible Show dismiss button (requires wads JS).
     *   @type bool   $banner      Use banner variant (left border only).
     * }
     */
    public static function notice(string $message, string $type = 'info', array $options = []): string
    {
        $classes = ['wads-notice', 'wads-notice--' . $type];
        if (!empty($options['banner'])) $classes[] = 'wads-notice--banner';

        $title = isset($options['title'])
            ? '<span class="wads-notice__title">' . esc_html($options['title']) . '</span>'
            : '';

        $dismiss = '';
        if (!empty($options['dismissible'])) {
            $dismiss = '<button class="wads-notice__dismiss" aria-label="Dismiss">&times;</button>';
        }

        return sprintf(
            '<div class="%s"><div class="wads-notice__content">%s%s</div>%s</div>',
            esc_attr(implode(' ', $classes)),
            $title,
            $message,
            $dismiss
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // BADGE
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a badge.
     *
     * @param string $label    Badge text.
     * @param string $variant  default | accent | success | warning | danger | info
     *                         solid-accent | solid-success | solid-danger
     *                         dot-success | dot-warning | dot-danger (dot prefix adds colored dot)
     */
    public static function badge(string $label, string $variant = 'default'): string
    {
        $classes = ['wads-badge'];

        if (str_starts_with($variant, 'dot-')) {
            $classes[] = 'wads-badge--dot';
            $classes[] = 'wads-badge--' . substr($variant, 4);
        } else {
            $classes[] = 'wads-badge--' . $variant;
        }

        return sprintf(
            '<span class="%s">%s</span>',
            esc_attr(implode(' ', $classes)),
            esc_html($label)
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // CHIP / TAG
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a chip/tag.
     *
     * @param string $label
     * @param string $variant  (default) | accent | success | danger
     * @param array  $options {
     *   @type bool   $removable  Add remove button (triggers JS dismiss).
     *   @type string $remove_value  Value passed on remove click.
     * }
     */
    public static function chip(string $label, string $variant = '', array $options = []): string
    {
        $classes = ['wads-chip'];
        if ($variant) $classes[] = 'wads-chip--' . $variant;

        $remove = '';
        if (!empty($options['removable'])) {
            $val = isset($options['remove_value']) ? esc_attr($options['remove_value']) : '';
            $remove = '<button class="wads-chip__remove" aria-label="Remove" data-value="' . $val . '">&times;</button>';
        }

        return sprintf(
            '<span class="%s">%s%s</span>',
            esc_attr(implode(' ', $classes)),
            esc_html($label),
            $remove
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // CARD
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a card.
     *
     * @param array $options {
     *   @type string $title     Card title (escaped).
     *   @type string $subtitle  Small text below title (escaped).
     *   @type string $header_end  HTML placed right-side of the header (badge, button...).
     *   @type string $body      Card body HTML (not escaped — use esc_html in your content).
     *   @type string $footer    Card footer HTML (not escaped).
     *   @type string $variant   (optional) flat | accent | clickable
     * }
     */
    public static function card(array $options): string
    {
        $classes = ['wads-card'];
        if (!empty($options['variant'])) $classes[] = 'wads-card--' . $options['variant'];

        $header = '';
        if (!empty($options['title'])) {
            $subtitle = !empty($options['subtitle'])
                ? '<p class="wads-card__subtitle">' . esc_html($options['subtitle']) . '</p>'
                : '';

            $header_end = $options['header_end'] ?? '';

            $header = sprintf(
                '<div class="wads-card__header"><div><p class="wads-card__title">%s</p>%s</div>%s</div>',
                esc_html($options['title']),
                $subtitle,
                $header_end
            );
        }

        $body = !empty($options['body'])
            ? '<div class="wads-card__body">' . $options['body'] . '</div>'
            : '';

        $footer = !empty($options['footer'])
            ? '<div class="wads-card__footer">' . $options['footer'] . '</div>'
            : '';

        return sprintf(
            '<div class="%s">%s%s%s</div>',
            esc_attr(implode(' ', $classes)),
            $header,
            $body,
            $footer
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // FORM INPUTS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a text input.
     *
     * @param string $name     input name attribute.
     * @param array  $options {
     *   @type string $id           Defaults to $name.
     *   @type string $type         text | password | email | url | number | tel (default: text)
     *   @type string $value
     *   @type string $placeholder
     *   @type string $size         sm | lg
     *   @type bool   $disabled
     *   @type bool   $error
     *   @type array  $attrs        Extra HTML attributes.
     * }
     */
    public static function input(string $name, array $options = []): string
    {
        $classes = ['wads-input'];
        if (!empty($options['size']))  $classes[] = 'wads-input--' . $options['size'];
        if (!empty($options['error'])) $classes[] = 'wads-input--error';

        $attrs = array_merge($options['attrs'] ?? [], [
            'type'        => $options['type'] ?? 'text',
            'name'        => $name,
            'id'          => $options['id'] ?? $name,
            'value'       => $options['value'] ?? '',
            'placeholder' => $options['placeholder'] ?? '',
        ]);

        if (!empty($options['disabled'])) $attrs['disabled'] = 'disabled';

        return sprintf('<input class="%s" %s>', esc_attr(implode(' ', $classes)), self::build_attrs($attrs));
    }

    /**
     * Render a <select> element.
     *
     * @param string $name
     * @param array  $choices  Assoc array: ['value' => 'Label', ...]
     *                         Or grouped: ['Group Label' => ['value' => 'Label', ...], ...]
     * @param array  $options {
     *   @type string $id
     *   @type string $selected  Currently selected value.
     *   @type bool   $disabled
     *   @type bool   $error
     *   @type string $placeholder  First empty option label.
     *   @type array  $attrs
     * }
     */
    public static function select(string $name, array $choices, array $options = []): string
    {
        $classes = ['wads-select'];
        if (!empty($options['error'])) $classes[] = 'wads-select--error';

        $attrs = array_merge($options['attrs'] ?? [], [
            'name' => $name,
            'id'   => $options['id'] ?? $name,
        ]);
        if (!empty($options['disabled'])) $attrs['disabled'] = 'disabled';

        $selected = $options['selected'] ?? '';

        $opts_html = '';
        if (!empty($options['placeholder'])) {
            $opts_html .= '<option value="">' . esc_html($options['placeholder']) . '</option>';
        }

        foreach ($choices as $value => $label) {
            if (is_array($label)) {
                // Grouped
                $opts_html .= '<optgroup label="' . esc_attr($value) . '">';
                foreach ($label as $v => $l) {
                    $sel = ((string)$v === (string)$selected) ? ' selected' : '';
                    $opts_html .= sprintf('<option value="%s"%s>%s</option>', esc_attr($v), $sel, esc_html($l));
                }
                $opts_html .= '</optgroup>';
            } else {
                $sel = ((string)$value === (string)$selected) ? ' selected' : '';
                $opts_html .= sprintf('<option value="%s"%s>%s</option>', esc_attr($value), $sel, esc_html($label));
            }
        }

        return sprintf(
            '<select class="%s" %s>%s</select>',
            esc_attr(implode(' ', $classes)),
            self::build_attrs($attrs),
            $opts_html
        );
    }

    /**
     * Render a <textarea>.
     *
     * @param string $name
     * @param string $value   Current value (will be escaped).
     * @param array  $options {
     *   @type string $id
     *   @type int    $rows   (default: 4)
     *   @type string $placeholder
     *   @type bool   $disabled
     *   @type bool   $error
     *   @type array  $attrs
     * }
     */
    public static function textarea(string $name, string $value = '', array $options = []): string
    {
        $classes = ['wads-textarea'];
        if (!empty($options['error'])) $classes[] = 'wads-textarea--error';

        $attrs = array_merge($options['attrs'] ?? [], [
            'name'        => $name,
            'id'          => $options['id'] ?? $name,
            'rows'        => $options['rows'] ?? 4,
            'placeholder' => $options['placeholder'] ?? '',
        ]);
        if (!empty($options['disabled'])) $attrs['disabled'] = 'disabled';

        return sprintf(
            '<textarea class="%s" %s>%s</textarea>',
            esc_attr(implode(' ', $classes)),
            self::build_attrs($attrs),
            esc_textarea($value)
        );
    }

    /**
     * Render a checkbox row.
     *
     * @param string $name
     * @param string $label    Visible label text.
     * @param array  $options {
     *   @type bool   $checked
     *   @type string $value   (default: '1')
     *   @type string $hint    Small description below label.
     *   @type bool   $disabled
     *   @type array  $attrs
     * }
     */
    public static function checkbox(string $name, string $label, array $options = []): string
    {
        $attrs = array_merge($options['attrs'] ?? [], [
            'type'  => 'checkbox',
            'name'  => $name,
            'id'    => $options['id'] ?? $name,
            'value' => $options['value'] ?? '1',
        ]);
        if (!empty($options['checked']))  $attrs['checked']  = 'checked';
        if (!empty($options['disabled'])) $attrs['disabled'] = 'disabled';

        $hint = !empty($options['hint'])
            ? '<span class="wads-check__hint">' . esc_html($options['hint']) . '</span>'
            : '';

        return sprintf(
            '<label class="wads-check"><input %s><div><span class="wads-check__label">%s</span>%s</div></label>',
            self::build_attrs($attrs),
            esc_html($label),
            $hint
        );
    }

    /**
     * Render a toggle switch.
     *
     * @param string $name
     * @param string $label
     * @param array  $options {
     *   @type bool   $checked
     *   @type string $value    (default: '1')
     *   @type bool   $disabled
     *   @type array  $attrs
     * }
     */
    public static function toggle(string $name, string $label, array $options = []): string
    {
        $attrs = array_merge($options['attrs'] ?? [], [
            'type'  => 'checkbox',
            'name'  => $name,
            'id'    => $options['id'] ?? $name,
            'value' => $options['value'] ?? '1',
        ]);
        if (!empty($options['checked']))  $attrs['checked']  = 'checked';
        if (!empty($options['disabled'])) $attrs['disabled'] = 'disabled';

        return sprintf(
            '<label class="wads-toggle"><input %s><span class="wads-toggle__track"></span><span class="wads-toggle__label">%s</span></label>',
            self::build_attrs($attrs),
            esc_html($label)
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // FORM GROUP (label + control + hint + error)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Wrap a control in a labelled form group.
     *
     * @param string $label       Visible label.
     * @param string $control_html  HTML of the input/select/etc. (already rendered).
     * @param array  $options {
     *   @type string $for       Label `for` attribute (defaults to nothing — set in your input).
     *   @type bool   $required  Appends a red asterisk.
     *   @type string $hint      Gray help text below the input.
     *   @type string $error     Red error message (also set error state on input yourself).
     * }
     */
    public static function form_group(string $label, string $control_html, array $options = []): string
    {
        $for_attr = !empty($options['for']) ? ' for="' . esc_attr($options['for']) . '"' : '';

        $required = !empty($options['required'])
            ? ' <span class="wads-required" aria-hidden="true">*</span>'
            : '';

        $hint = !empty($options['hint'])
            ? '<span class="wads-hint">' . esc_html($options['hint']) . '</span>'
            : '';

        $error = !empty($options['error'])
            ? '<span class="wads-field-error" role="alert">' . esc_html($options['error']) . '</span>'
            : '';

        return sprintf(
            '<div class="wads-form-group"><label class="wads-label"%s>%s%s</label>%s%s%s</div>',
            $for_attr,
            esc_html($label),
            $required,
            $control_html,
            $hint,
            $error
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // SETTINGS SECTION
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a full settings section (the main layout building block).
     *
     * @param array $options {
     *   @type string   $title
     *   @type string   $desc     Optional subtitle.
     *   @type array    $rows     Array of setting rows: ['label', 'desc', 'control', 'required']
     *   @type string   $footer   HTML placed in the section footer (usually a save button).
     *   @type string   $body     Alternative: raw HTML body instead of rows.
     * }
     *
     * Example:
     *   Components::settings_section([
     *       'title' => 'API Connection',
     *       'desc'  => 'Bsale credentials.',
     *       'rows'  => [
     *           [
     *               'label'   => 'Access Token',
     *               'desc'    => 'Found in Bsale → Settings → API.',
     *               'control' => Components::input('bsale_dte_api_token', ['type' => 'password']),
     *           ],
     *           [
     *               'label'   => 'Sandbox Mode',
     *               'control' => Components::toggle('bsale_dte_sandbox', 'Enable sandbox'),
     *           ],
     *       ],
     *       'footer' => Components::button('Save Changes'),
     *   ]);
     */
    public static function settings_section(array $options): string
    {
        $desc = !empty($options['desc'])
            ? '<p class="wads-settings-section__desc">' . esc_html($options['desc']) . '</p>'
            : '';

        $header = sprintf(
            '<div class="wads-settings-section__header"><p class="wads-settings-section__title">%s</p>%s</div>',
            esc_html($options['title'] ?? ''),
            $desc
        );

        // Body: rows or raw HTML
        if (!empty($options['body'])) {
            $body_inner = $options['body'];
        } elseif (!empty($options['rows'])) {
            $rows_html = '';
            foreach ($options['rows'] as $row) {
                $rows_html .= self::setting_row(
                    $row['label'] ?? '',
                    $row['control'] ?? '',
                    [
                        'desc'     => $row['desc'] ?? '',
                        'required' => $row['required'] ?? false,
                    ]
                );
            }
            $body_inner = $rows_html;
        } else {
            $body_inner = '';
        }

        $body = '<div class="wads-settings-section__body">' . $body_inner . '</div>';

        $footer = !empty($options['footer'])
            ? '<div class="wads-settings-section__footer">' . $options['footer'] . '</div>'
            : '';

        return '<div class="wads-settings-section">' . $header . $body . $footer . '</div>';
    }

    /**
     * Render a single setting row (label column + control column).
     *
     * @param string $label
     * @param string $control_html
     * @param array  $options {
     *   @type string $desc      Small description below the label.
     *   @type bool   $required
     * }
     */
    public static function setting_row(string $label, string $control_html, array $options = []): string
    {
        $required = !empty($options['required'])
            ? ' <span class="wads-required">*</span>'
            : '';

        $desc = !empty($options['desc'])
            ? '<span class="wads-setting-row__desc">' . esc_html($options['desc']) . '</span>'
            : '';

        return sprintf(
            '<div class="wads-setting-row">
                <div class="wads-setting-row__meta">
                    <span class="wads-setting-row__label">%s%s</span>%s
                </div>
                <div class="wads-setting-row__control">%s</div>
            </div>',
            esc_html($label),
            $required,
            $desc,
            $control_html
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // PAGE HEADER
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a page header.
     *
     * @param string $title
     * @param array  $options {
     *   @type string $desc     Subtitle.
     *   @type string $badge    Badge label (displayed after title, solid-accent variant).
     *   @type string $actions  HTML placed in the right column (buttons, etc.).
     * }
     */
    public static function page_header(string $title, array $options = []): string
    {
        $desc = !empty($options['desc'])
            ? '<p class="wads-page-header__desc">' . esc_html($options['desc']) . '</p>'
            : '';

        $badge = !empty($options['badge'])
            ? ' ' . self::badge($options['badge'], 'solid-accent')
            : '';

        $actions = !empty($options['actions'])
            ? '<div class="wads-page-header__actions">' . $options['actions'] . '</div>'
            : '';

        return sprintf(
            '<div class="wads-page-header">
                <div class="wads-page-header__content">
                    <h1 class="wads-page-header__title">%s%s</h1>%s
                </div>%s
            </div>',
            esc_html($title),
            $badge,
            $desc,
            $actions
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // STAT CARD
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a stat card.
     *
     * @param string $label  Metric name.
     * @param string $value  Main value.
     * @param array  $options {
     *   @type string $change  Change description (e.g. '+12% this month').
     *   @type string $trend   up | down (colors the change text).
     * }
     */
    public static function stat(string $label, string $value, array $options = []): string
    {
        $change_class = '';
        if (!empty($options['trend'])) {
            $change_class = ' wads-stat__change--' . $options['trend'];
        }

        $change = !empty($options['change'])
            ? '<span class="wads-stat__change' . $change_class . '">' . esc_html($options['change']) . '</span>'
            : '';

        return sprintf(
            '<div class="wads-stat">
                <span class="wads-stat__label">%s</span>
                <span class="wads-stat__value">%s</span>%s
            </div>',
            esc_html($label),
            esc_html($value),
            $change
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // PROGRESS BAR
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a progress bar.
     *
     * @param int   $value   Current value.
     * @param int   $max     Maximum value (default: 100).
     * @param array $options {
     *   @type string $variant  (default) | success | danger
     *   @type string $label    Optional text displayed above the bar.
     * }
     */
    public static function progress(int $value, int $max = 100, array $options = []): string
    {
        $percent = $max > 0 ? min(100, round(($value / $max) * 100)) : 0;

        $class = 'wads-progress';
        if (!empty($options['variant'])) $class .= ' wads-progress--' . $options['variant'];

        $label = !empty($options['label'])
            ? '<div class="wads-between wads-mb-2"><span class="wads-caption">' . esc_html($options['label']) . '</span>'
              . '<span class="wads-text-sm wads-text-muted">' . esc_html($value . ' / ' . $max) . '</span></div>'
            : '';

        return $label . sprintf(
            '<div class="%s" role="progressbar" aria-valuenow="%d" aria-valuemin="0" aria-valuemax="%d">
                <div class="wads-progress__bar" style="width:%d%%"></div>
            </div>',
            esc_attr($class),
            $value,
            $max,
            $percent
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // SPINNER
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a loading spinner.
     *
     * @param string $size  sm | (default) | lg
     */
    public static function spinner(string $size = ''): string
    {
        $class = 'wads-spinner';
        if ($size) $class .= ' wads-spinner--' . $size;

        return '<span class="' . esc_attr($class) . '" role="status" aria-label="Loading"></span>';
    }

    // ─────────────────────────────────────────────────────────────────
    // EMPTY STATE
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render an empty state block.
     *
     * @param string $title
     * @param array  $options {
     *   @type string $desc    Description text.
     *   @type string $action  HTML for an action button.
     * }
     */
    public static function empty_state(string $title, array $options = []): string
    {
        $desc = !empty($options['desc'])
            ? '<p class="wads-empty__desc">' . esc_html($options['desc']) . '</p>'
            : '';

        $action = $options['action'] ?? '';

        return sprintf(
            '<div class="wads-empty"><p class="wads-empty__title">%s</p>%s%s</div>',
            esc_html($title),
            $desc,
            $action
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // BREADCRUMBS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render breadcrumbs navigation.
     *
     * @param array $items  Array of ['label' => 'Home', 'url' => '#'] — last item is current (no link).
     */
    public static function breadcrumbs(array $items): string
    {
        $html   = '<nav class="wads-breadcrumbs" aria-label="Breadcrumb">';
        $total  = count($items);

        foreach ($items as $i => $item) {
            $is_last = ($i === $total - 1);
            $html   .= '<span class="wads-breadcrumbs__item">';

            if ($is_last) {
                $html .= '<span class="wads-breadcrumbs__current" aria-current="page">' . esc_html($item['label']) . '</span>';
            } else {
                $html .= '<a class="wads-breadcrumbs__link" href="' . esc_url($item['url'] ?? '#') . '">' . esc_html($item['label']) . '</a>';
                $html .= '<span class="wads-breadcrumbs__sep" aria-hidden="true">/</span>';
            }

            $html .= '</span>';
        }

        $html .= '</nav>';
        return $html;
    }

    // ─────────────────────────────────────────────────────────────────
    // CALLOUT
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a callout block (stronger than notice, for in-content emphasis).
     *
     * @param string $title
     * @param string $type   info | success | warning | danger | accent
     * @param string $body   Content HTML (not escaped internally).
     */
    public static function callout(string $title, string $type = 'info', string $body = ''): string
    {
        return sprintf(
            '<div class="wads-callout wads-callout--%s"><p class="wads-callout__title">%s</p><p>%s</p></div>',
            esc_attr($type),
            esc_html($title),
            $body
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // KEY-VALUE LIST
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render a key-value definition list.
     *
     * @param array $pairs  Assoc array ['Key' => 'Value', ...]
     *                      Values can be HTML strings (not escaped internally).
     */
    public static function kv_list(array $pairs): string
    {
        $rows = '';
        foreach ($pairs as $key => $value) {
            $rows .= sprintf(
                '<div class="wads-kv-row"><div class="wads-kv-key">%s</div><div class="wads-kv-value">%s</div></div>',
                esc_html($key),
                $value  // Allow HTML values (callers are responsible for escaping)
            );
        }

        return '<div class="wads-kv-list">' . $rows . '</div>';
    }

    // ─────────────────────────────────────────────────────────────────
    // INTERNAL HELPERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Build an HTML attribute string from an associative array.
     * Skips null and empty-string values (except value="").
     */
    private static function build_attrs(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $key => $value) {
            if ($value === null) continue;

            $key = esc_attr($key);

            // Boolean attributes
            if (in_array($key, ['disabled', 'checked', 'selected', 'readonly', 'multiple', 'required', 'autofocus'], true)) {
                if ($value) $parts[] = $key;
                continue;
            }

            $parts[] = $key . '="' . esc_attr((string)$value) . '"';
        }

        return implode(' ', $parts);
    }
}
