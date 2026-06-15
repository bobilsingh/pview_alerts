<?php

// Vis-network data builders for the workflow designer and ticket progress diagram.

if (!function_exists('flow_ticket_ancestor_ids')) {
    // Returns all state IDs upstream of $currentStateId via forward transitions (falls back to parent_state_id).
    function flow_ticket_ancestor_ids($states, $currentStateId, $transitions = [])
    {
        // Build reverse-forward adjacency: for each state, which states point TO it
        // via a forward transition?  BFS from currentStateId through this reverse
        // map gives us everything "upstream" of the current state.
        if (!empty($transitions)) {
            $revFwd = [];
            foreach ($transitions as $t) {
                if (($t['transition_type'] ?? 'forward') !== 'forward') {
                    continue;
                }
                $from = (int) $t['from_state_id'];
                $to   = (int) $t['to_state_id'];
                $revFwd[$to][] = $from;
            }
            if (!empty($revFwd)) {
                $ancestors = [];
                $queue     = [$currentStateId];
                $visited   = [$currentStateId => true];
                $guard     = 0;
                while (!empty($queue) && $guard < 200) {
                    $guard++;
                    $node = array_shift($queue);
                    foreach ($revFwd[$node] ?? [] as $prev) {
                        if (!isset($visited[$prev])) {
                            $visited[$prev]    = true;
                            $ancestors[$prev]  = true;
                            $queue[]           = $prev;
                        }
                    }
                }
                return $ancestors;
            }
        }
        $byId     = [];
        foreach ($states as $s) {
            $byId[(int) $s['id']] = $s;
        }
        $ancestors = [];
        $cursor    = $currentStateId;
        $guard     = 0;
        while ($cursor > 0 && isset($byId[$cursor]) && $guard < 100) {
            $row    = $byId[$cursor];
            $parent = 0;
            if (isset($row['parent_state_id'])) {
                $parent = (int) $row['parent_state_id'];
            }
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

if (!function_exists('flow_vis_edges')) {
    // Returns edges for the workflow diagram; priority: explicit transitions → parent tree → sequential sort_order.
    function flow_vis_edges($states, $transitions = [])
    {
        // Collect only forward edges from the transitions table.
        $fwdEdges = [];
        foreach ($transitions as $t) {
            if (($t['transition_type'] ?? 'forward') === 'forward') {
                $fwdEdges[] = [
                    'from'            => (int) $t['from_state_id'],
                    'to'              => (int) $t['to_state_id'],
                    'transition_type' => 'forward',
                ];
            }
        }

        if (!empty($fwdEdges)) {
            return $fwdEdges;
        }

        $stateIds       = [];
        foreach ($states as $s) {
            $stateIds[] = (int) $s['id'];
        }
        $hasParentLinks = false;
        $parentSet      = []; // IDs that ARE a parent of at least one other state
        foreach ($states as $s) {
            $pid = 0;
            if (isset($s['parent_state_id'])) {
                $pid = (int) $s['parent_state_id'];
            }
            if ($pid > 0 && in_array($pid, $stateIds, true)) {
                $fwdEdges[]      = ['from' => $pid, 'to' => (int) $s['id'], 'transition_type' => 'forward'];
                $parentSet[$pid] = true;
                $hasParentLinks  = true;
            }
        }
        if ($hasParentLinks) {
            // Leaf states implicitly route to the single closing/final state.
            $closingId = null;
            foreach ($states as $s) {
                if (!empty($s['is_final'])) {
                    $closingId = (int) $s['id'];
                    break;
                }
            }
            if ($closingId !== null) {
                foreach ($states as $s) {
                    $sid = (int) $s['id'];
                    if ($sid === $closingId) {
                        continue;
                    }
                    if (isset($parentSet[$sid])) {
                        continue;
                    }
                    $fwdEdges[] = ['from' => $sid, 'to' => $closingId, 'transition_type' => 'forward'];
                }
            }
            return $fwdEdges;
        }

        $count = count($states);
        for ($i = 0; $i < $count - 1; $i++) {
            $fwdEdges[] = [
                'from'            => (int) $states[$i]['id'],
                'to'              => (int) $states[$i + 1]['id'],
                'transition_type' => 'forward',
            ];
        }
        return $fwdEdges;
    }
}

if (!function_exists('flow_vis_designer_data')) {
    // Returns vis-network nodes+edges for the designer preview widget.
    function flow_vis_designer_data($states, $transitions = [])
    {
        if (empty($states)) {
            return ['nodes' => [], 'edges' => []];
        }
        $nodes = [];
        foreach ($states as $s) {
            $type = 'process';
            if (!empty($s['is_initial'])) {
                $type = 'initial';
            } elseif (!empty($s['is_final'])) {
                $type = 'final';
            }
            $nodes[] = [
                'id'    => (int) $s['id'],
                'label' => (string) $s['name'],
                'type'  => $type,
            ];
        }
        $edges = flow_vis_edges($states, $transitions);
        foreach ($transitions as $t) {
            if (($t['transition_type'] ?? '') === 'backward') {
                $edges[] = [
                    'from'            => (int) $t['from_state_id'],
                    'to'              => (int) $t['to_state_id'],
                    'transition_type' => 'backward',
                ];
            }
        }
        return ['nodes' => $nodes, 'edges' => $edges];
    }
}

if (!function_exists('flow_vis_ticket_data')) {
    // Returns vis-network nodes+edges for the ticket detail widget (node status: passed|current|pending).
    function flow_vis_ticket_data($states, $currentStateId, $transitions = [])
    {
        if (empty($states)) {
            return ['nodes' => [], 'edges' => []];
        }
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
            if (($currentStateId === 0 || !in_array($currentStateId, $stateIds, true)) && !empty($states)) {
                $currentStateId = (int) $states[0]['id'];
            }
        }
        $ancestors = flow_ticket_ancestor_ids($states, $currentStateId, $transitions);

        $nodes = [];
        foreach ($states as $s) {
            $sid = (int) $s['id'];
            if ($sid === $currentStateId) {
                $status = 'current';
            } elseif (isset($ancestors[$sid])) {
                $status = 'passed';
            } else {
                $status = 'pending';
            }
            $nodes[] = ['id' => $sid, 'label' => (string) $s['name'], 'status' => $status];
        }
        $edges = flow_vis_edges($states, $transitions);
        foreach ($transitions as $t) {
            if (($t['transition_type'] ?? '') === 'backward') {
                $edges[] = [
                    'from'            => (int) $t['from_state_id'],
                    'to'              => (int) $t['to_state_id'],
                    'transition_type' => 'backward',
                ];
            }
        }
        return ['nodes' => $nodes, 'edges' => $edges];
    }
}

if (!function_exists('flow_widget_html')) {
    // Wraps vis-network node/edge data in the standard widget chrome (toolbar + canvas + legend).
    function flow_widget_html($visData, $opts = [])
    {
        $subtitle = 'How tickets travel through this flow';
        if (isset($opts['subtitle'])) {
            $subtitle = (string) $opts['subtitle'];
        }
        $showLegend = true;
        if (isset($opts['legend'])) {
            $showLegend = (bool) $opts['legend'];
        }
        $variant = 'designer';
        if (isset($opts['variant'])) {
            $variant = (string) $opts['variant'];
        }

        // JSON_HEX_TAG prevents </script> from breaking the embedded JSON block.
        $dataJson = json_encode($visData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

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
        $html .= '  <div class="flow-vis-wrap" data-flow-canvas>';
        $html .= '    <div class="flow-vis-container"></div>';
        $html .= '    <script type="application/json" class="flow-vis-data">' . $dataJson . '</script>';
        $html .= '  </div>';
        if ($showLegend) {
            $html .= '  <div class="flow-widget-legend">';
            $html .= '    <span class="fw-lg"><span class="fw-lg-dot fw-lg-initial"></span> Start</span>';
            $html .= '    <span class="fw-lg"><span class="fw-lg-dot fw-lg-process"></span> Process</span>';
            $html .= '    <span class="fw-lg"><span class="fw-lg-dot fw-lg-final"></span> End</span>';
            $html .= '    <span class="fw-lg"><span class="fw-lg-arrow"></span> Forward</span>';
            $html .= '    <span class="fw-lg"><span class="fw-lg-arrow fw-lg-arrow--back"></span> Send back</span>';
            $html .= '  </div>';
        }
        $html .= '</div>';
        return $html;
    }
}
