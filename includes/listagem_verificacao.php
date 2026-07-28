<?php

if (!function_exists('lv_e')) {
    function lv_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('lv_label')) {
    function lv_label($field, array $labels = [])
    {
        if (isset($labels[$field])) {
            return $labels[$field];
        }

        return ucfirst(str_replace('_', ' ', (string) $field));
    }
}

if (!function_exists('lv_value')) {
    function lv_value($value)
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}

if (!function_exists('lv_render_verification_modal')) {
    function lv_render_verification_modal($modalId, $title, array $data, array $labels = [], array $exclude = [])
    {
        $excludeMap = array_fill_keys($exclude, true);
        ?>
        <div class="modal fade" id="<?php echo lv_e($modalId); ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header border-0">
                        <h5 class="modal-title"><?php echo lv_e($title); ?></h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <tbody>
                                    <?php foreach ($data as $field => $value): ?>
                                        <?php if (isset($excludeMap[$field])) { continue; } ?>
                                        <tr>
                                            <th style="width: 35%;"><?php echo lv_e(lv_label($field, $labels)); ?></th>
                                            <td><?php echo nl2br(lv_e(lv_value($value))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
