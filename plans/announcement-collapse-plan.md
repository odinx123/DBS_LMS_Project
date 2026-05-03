# 公告摺疊功能修改計劃

## 目標
修改 `student_course.php` 中的公告顯示，使其預設為摺疊狀態，點擊「查看內容」按鈕後才會展開。

## 需要修改的檔案
- `student_course.php`

## 具體修改內容

### 1. 修改初始頁面載入時的公告顯示（第 262-270 行）
**原始程式碼：**
```php
<?php foreach ($announcements as $ann): ?>
    <div class="list-group-item">
        <div class="d-flex justify-content-between">
            <strong><?php echo htmlspecialchars($ann['Title'], ENT_QUOTES, 'UTF-8'); ?></strong>
            <span class="text-muted small"><?php echo htmlspecialchars($ann['Publish_Time'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="mt-2" style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($ann['Content'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
    </div>
<?php endforeach; ?>
```

**修改後程式碼：**
```php
<?php foreach ($announcements as $index => $ann): ?>
    <?php $collapseId = 'collapse-' . $index; ?>
    <div class="list-group-item">
        <div class="d-flex justify-content-between">
            <strong><?php echo htmlspecialchars($ann['Title'], ENT_QUOTES, 'UTF-8'); ?></strong>
            <span class="text-muted small"><?php echo htmlspecialchars($ann['Publish_Time'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php if (!empty($ann['Content'])): ?>
            <div class="mt-2">
                <button class="btn btn-sm btn-outline-secondary mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>">
                    查看內容
                </button>
                <div class="collapse" id="<?php echo $collapseId; ?>">
                    <div class="card card-body" style="white-space: pre-wrap;">
                        <?php echo nl2br(htmlspecialchars($ann['Content'], ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
```

### 2. 修改 AJAX 請求中的公告 HTML 生成（第 74-84 行）
**原始程式碼：**
```php
echo '<div class="list-group-item">';
echo '<div class="d-flex justify-content-between">';
echo '<strong>' . htmlspecialchars($ann['Title'], ENT_QUOTES, 'UTF-8') . '</strong>';
echo '<span class="text-muted small">' . htmlspecialchars($ann['Publish_Time'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>';
echo '</div>';
echo '<div class="mt-2" style="white-space: pre-wrap;">' . nl2br(htmlspecialchars($ann['Content'] ?? '', ENT_QUOTES, 'UTF-8')) . '</div>';
echo '</div>';
```

**修改後程式碼：**
```php
$collapseId = 'collapse-ajax-' . $ann['Announce_ID'];
echo '<div class="list-group-item">';
echo '<div class="d-flex justify-content-between">';
echo '<strong>' . htmlspecialchars($ann['Title'], ENT_QUOTES, 'UTF-8') . '</strong>';
echo '<span class="text-muted small">' . htmlspecialchars($ann['Publish_Time'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>';
echo '</div>';
if (!empty($ann['Content'])) {
    echo '<div class="mt-2">';
    echo '<button class="btn btn-sm btn-outline-secondary mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="false" aria-controls="' . $collapseId . '">';
    echo '查看內容';
    echo '</button>';
    echo '<div class="collapse" id="' . $collapseId . '">';
    echo '<div class="card card-body" style="white-space: pre-wrap;">';
    echo nl2br(htmlspecialchars($ann['Content'], ENT_QUOTES, 'UTF-8'));
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
echo '</div>';
```

### 3. 加入 Bootstrap JavaScript 支援
**需要修改的位置：** 在 `</body>` 標籤前（約第 410 行）

**原始程式碼：**
```html
</script>
</body>
</html>
```

**修改後程式碼：**
```html
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

## 測試計劃
1. 載入 `student_course.php` 頁面
2. 選擇一個有公告的課程
3. 切換到「公告」標籤頁
4. 確認公告內容預設為摺疊狀態
5. 點擊「查看內容」按鈕，確認內容正確展開
6. 再次點擊按鈕，確認內容可以摺疊
7. 切換到其他標籤頁（作業/成績），再切換回公告標籤頁，確認 AJAX 載入的公告也有摺疊功能

## 注意事項
1. 確保每個公告有唯一的 `collapseId` 以避免衝突
2. 空內容的公告不需要顯示摺疊按鈕
3. Bootstrap JavaScript 必須載入才能使用摺疊功能
