<?php

// Flow Mermaid source builders for the designer preview and the ticket
// progress diagram. Views render with flow_widget_html() which wraps the
// generated Mermaid source in the standard widget chrome.

if (!function_exists('flow_ticket_ancestor_ids')) {
    // All state IDs the ticket passed through to reach $currentStateId.
    function flow_ticket_ancestor_ids(array $states, int $currentStateId): array
    {
        $byId = [];
        foreach ($states as $s) {
            $byId[(int) $s['id']] = $s;
        }
        $ancestors = [];
        $cursor    = $currentStateId;
        $guard     = 0;
        while ($cursor > 0 && isset($byId[$cursor]) && $guard < 100) {
            $row    = $byId[$cursor];
            $parent = isset($row['parent_state_id']) ? (int) $row['parent_state_id'] : 0;
            if ($parent <= 0 || !isset($byId[$parent])) {
                break;
            }
            $ancestors[$parent] = true;
            $cursor = $parent;
            $guard++;
        }
        return $ancestors;
    }
}

if (!function_exists('flow_mermaid_label')) {
    // Escape a state name for use as a Mermaid double-quoted node label.
    function flow_mermaid_label(string $name): string
    {
        $clean = str_replace(["\\", "\"", "\n", "\r"], ["\\\\", "\\\"", ' ', ' '], $name);
        return '"' . $clean . '"';
    }
}

if (!function_exists('flow_mermaid_edges')) {
    // Returns edge lines (parent --> child). Falls back to a linear chain
    // when no parent_state_id links exist.
    function flow_mermaid_edges(array $states): array
    {
        $stateIds = [];
        foreach ($states as $s) {
            $stateIds[] = (int) $s['id'];
        }
        $lines = [];
        $hasParentLinks = false;
        foreach ($states as $s) {
            $pid = isset($s['parent_state_id']) ? (int) $s['parent_state_id'] : 0;
            if ($pid > 0 && in_array($pid, $stateIds, true)) {
                $lines[] = '  s' . $pid . ' --> s' . (int) $s['id'];
                $hasParentLinks = true;
            }
        }
        if (!$hasParentLinks) {
            $count = count($states);
            for ($i = 0; $i < $count - 1; $i++) {
                $lines[] = '  s' . (int) $states[$i]['id'] . ' --> s' . (int) $states[$i + 1]['id'];
            }
        }
        return $lines;
    }
}

if (!function_exists('flow_mermaid_designer_source')) {
    // classDef is a stub — all visual styling is in CSS (.flow-widget selectors).
    function flow_mermaid_designer_source(array $states): string
    {
        if (empty($states)) {
            return '';
        }
        $lines = ['flowchart LR'];
        foreach ($states as $s) {
            $id    = 's' . (int) $s['id'];
            $label = flow_mermaid_label((string) $s['name']);
            if (!empty($s['is_initial']) || !empty($s['is_final'])) {
                $lines[] = '  ' . $id . '([' . $label . '])';
            } else {
                $lines[] = '  ' . $id . '[' . $label . ']';
            }
        }
        foreach (flow_mermaid_edges($states) as $edgeLine) {
            $lines[] = $edgeLine;
        }
        $lines[] = '  classDef initialState font-weight:600';
        $lines[] = '  classDef finalState font-weight:600';
        $lines[] = '  classDef processState font-weight:600';
        foreach ($states as $s) {
            $id = 's' . (int) $s['id'];
            if (!empty($s['is_initial'])) {
                $lines[] = '  class ' . $id . ' initialState';
            } else if (!empty($s['is_final'])) {
                $lines[] = '  class ' . $id . ' finalState';
            } else {
                $lines[] = '  class ' . $id . ' processState';
            }
        }
        return implode("\n", $lines);
    }
}

if (!function_exists('flow_mermaid_ticket_source')) {
    function flow_mermaid_ticket_source(array $states, int $currentStateId): string
    {
        if (empty($states)) {
            return '';
        }
        // Fall back to initial state if current_state_id is missing or stale.
        $stateIds = [];
        foreach ($states as $s) {
            $stateIds[] = (int) $s['id'];
        }
        if ($currentStateId === 0 || !in_array($currentStateId, $stateIds, true)) {
            foreach ($states as $s) {
                if (!empty($s['is_initial'])) {
                    $currentStateId = (int) $s['id'];
                    break;
                }
            }
            // Still nothing? Use the first state in the list.
            if (($currentStateId === 0 || !in_array($currentStateId, $stateIds, true)) && !empty($states)) {
                $currentStateId = (int) $states[0]['id'];
            }
        }
        $ancestors = flow_ticket_ancestor_ids($states, $currentStateId);

        $lines = ['flowchart LR'];
        foreach ($states as $s) {
            $id    = 's' . (int) $s['id'];
            $label = flow_mermaid_label((string) $s['name']);
            if (!empty($s['is_initial']) || !empty($s['is_final'])) {
                $lines[] = '  ' . $id . '([' . $label . '])';
            } else {
                $lines[] = '  ' . $id . '[' . $label . ']';
            }
        }
        foreach (flow_mermaid_edges($states) as $edgeLine) {
            $lines[] = $edgeLine;
        }
        $lines[] = '  classDef passed font-weight:600';
        $lines[] = '  classDef current font-weight:700';
        $lines[] = '  classDef pending font-weight:500';
        foreach ($states as $s) {
            $id  = 's' . (int) $s['id'];
            $sid = (int) $s['id'];
            if ($sid === $currentStateId) {
                $lines[] = '  class ' . $id . ' current';
            } else if (isset($ancestors[$sid])) {
                $lines[] = '  class ' . $id . ' passed';
            } else {
                $lines[] = '  class ' . $id . ' pending';
            }
        }
        return implode("\n", $lines);
    }
}

if (!function_exists('flow_widget_html')) {
    // Wraps a Mermaid source in the widget chrome (toolbar + canvas + legend).
    // $opts: subtitle, legend (bool), variant ('designer'|'ticket')
    function flow_widget_html(string $mermaidSource, array $opts = []): string
    {
        $subtitle   = isset($opts['subtitle']) ? (string) $opts['subtitle'] : 'How tickets travel through this flow';
        $showLegend = isset($opts['legend']) ? (bool) $opts['legend'] : true;
        $variant    = isset($opts['variant']) ? (string) $opts['variant'] : 'designer';

        $html  = '<div class="flow-widget" data-flow-variant="' . esc($variant) . '">';
        $html .= '  <div class="flow-widget-toolbar">';
        $html .= '    <div class="flow-widget-meta">';
        $html .= '      <i class="bi bi-diagram-3"></i>';
        $html .= '      <span class="flow-widget-subtitle">' . esc($subtitle) . '</span>';
        $html .= '    </div>';
        $html .= '    <div class="flow-widget-controls">';
        $html .= '      <button type="button" class="fw-btn" data-flow-fit title="Fit to view" aria-label="Fit to view"><i class="bi bi-aspect-ratio"></i><span class="fw-btn-label">Fit</span></button>';
        $html .= '      <button type="button" class="fw-btn fw-btn--icon" data-flow-zoom-out title="Zoom out" aria-label="Zoom out"><i class="bi bi-dash-lg"></i></button>';
        $html .= '      <span class="fw-zoom-pct" data-flow-zoom-pct>100%</span>';
        $html .= '      <button type="button" class="fw-btn fw-btn--icon" data-flow-zoom-in title="Zoom in" aria-label="Zoom in"><i class="bi bi-plus-lg"></i></button>';
        $html .= '      <button type="button" class="fw-btn fw-btn--icon" data-flow-fullscreen title="Fullscreen" aria-label="Fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>';
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '  <div class="flow-mermaid-wrap" data-flow-canvas>';
        $html .= '<pre class="mermaid">' . $mermaidSource . '</pre>';
        $html .= '  </div>';
        if ($showLegend) {
            $html .= '  <div class="flow-widget-legend">';
            $html .= '    <span class="fw-lg"><span class="fw-lg-dot fw-lg-initial"></span> Start</span>';
            $html .= '    <span class="fw-lg"><span class="fw-lg-dot fw-lg-process"></span> Process</span>';
            $html .= '    <span class="fw-lg"><span class="fw-lg-dot fw-lg-final"></span> End</span>';
            $html .= '    <span class="fw-lg"><span class="fw-lg-arrow"></span> Transition</span>';
            $html .= '  </div>';
        }
        $html .= '</div>';
        return $html;
    }
}
