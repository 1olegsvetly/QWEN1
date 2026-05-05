<?php
/**
 * Product Data Splitting System
 * Автоматически разбивает products.json на несколько файлов при превышении лимита размера
 */

// Максимальный размер одного JSON-файла в байтах (5 MB)
define('PRODUCTS_MAX_FILE_SIZE', 5 * 1024 * 1024);

// Префикс для файлов разбитых товаров
define('PRODUCTS_SPLIT_PREFIX', 'products_part_');

/**
 * Получить список всех файлов товаров (основной и разбитые)
 */
function getProductsFileList() {
    $dataDir = __DIR__ . '/../data/';
    $files = [];
    
    // Основной файл
    if (file_exists($dataDir . 'products.json')) {
        $files[] = 'products';
    }
    
    // Разбитые файлы
    $pattern = $dataDir . PRODUCTS_SPLIT_PREFIX . '*.json';
    foreach (glob($pattern) as $file) {
        $basename = basename($file, '.json');
        $files[] = $basename;
    }
    
    // Сортируем: основной файл первым, затем по номерам
    usort($files, function($a, $b) {
        if ($a === 'products') return -1;
        if ($b === 'products') return 1;
        
        preg_match('/(\d+)/', $a, $ma);
        preg_match('/(\d+)/', $b, $mb);
        
        $numA = isset($ma[1]) ? (int)$ma[1] : 0;
        $numB = isset($mb[1]) ? (int)$mb[1] : 0;
        
        return $numA - $numB;
    });
    
    return $files;
}

/**
 * Получить все товары из всех файлов
 */
function getProductsFromAllFiles() {
    $allProducts = [];
    $files = getProductsFileList();
    
    foreach ($files as $file) {
        $data = loadData($file);
        if (is_array($data)) {
            $allProducts = array_merge($allProducts, $data);
        }
    }
    
    return $allProducts;
}

/**
 * Проверить, нужна ли разбивка товаров
 */
function shouldSplitProducts($products) {
    $json = json_encode($products, JSON_UNESCAPED_UNICODE);
    return strlen($json) > PRODUCTS_MAX_FILE_SIZE;
}

/**
 * Разбить товары на несколько файлов
 */
function splitProductsIntoFiles($products) {
    global $_dataCache;
    
    $dataDir = __DIR__ . '/../data/';
    $currentPart = [];
    $partNumber = 1;
    $currentSize = 0;
    
    // Очищаем старые разбитые файлы
    foreach (glob($dataDir . PRODUCTS_SPLIT_PREFIX . '*.json') as $file) {
        @unlink($file);
    }
    
    // Разбиваем товары
    foreach ($products as $product) {
        $productJson = json_encode($product, JSON_UNESCAPED_UNICODE);
        $productSize = strlen($productJson);
        
        // Если добавление этого товара превысит лимит, сохраняем текущую часть
        if ($currentSize + $productSize > PRODUCTS_MAX_FILE_SIZE && !empty($currentPart)) {
            $partFile = PRODUCTS_SPLIT_PREFIX . $partNumber;
            saveData($partFile, $currentPart);
            $currentPart = [];
            $currentSize = 0;
            $partNumber++;
        }
        
        $currentPart[] = $product;
        $currentSize += $productSize;
    }
    
    // Сохраняем последнюю часть
    if (!empty($currentPart)) {
        $partFile = PRODUCTS_SPLIT_PREFIX . $partNumber;
        saveData($partFile, $currentPart);
    }
    
    // Очищаем основной файл (или оставляем пустым массивом)
    saveData('products', []);
    
    // Очищаем кэш
    unset($_dataCache['products']);
    for ($i = 1; $i <= $partNumber; $i++) {
        unset($_dataCache[PRODUCTS_SPLIT_PREFIX . $i]);
    }
}

/**
 * Объединить разбитые файлы обратно в основной
 */
function mergeProductsFromFiles() {
    global $_dataCache;
    
    $allProducts = getProductsFromAllFiles();
    
    // Сохраняем всё в основной файл
    saveData('products', $allProducts);
    
    // Удаляем разбитые файлы
    $dataDir = __DIR__ . '/../data/';
    foreach (glob($dataDir . PRODUCTS_SPLIT_PREFIX . '*.json') as $file) {
        @unlink($file);
    }
    
    // Очищаем кэш
    unset($_dataCache['products']);
    foreach (glob($dataDir . PRODUCTS_SPLIT_PREFIX . '*.json') as $file) {
        $basename = basename($file, '.json');
        unset($_dataCache[$basename]);
    }
}

/**
 * Переопределённая функция getProducts с поддержкой разбивки
 */
function getProductsWithSplitSupport() {
    $files = getProductsFileList();
    
    if (empty($files)) {
        return [];
    }
    
    // Если есть только основной файл и он не переполнен, возвращаем его
    if (count($files) === 1 && $files[0] === 'products') {
        return loadData('products');
    }
    
    // Иначе объединяем все файлы
    return getProductsFromAllFiles();
}

/**
 * Сохранить товары с автоматической разбивкой
 */
function saveProductsWithAutoSplit($products) {
    global $_dataCache;
    
    if (shouldSplitProducts($products)) {
        splitProductsIntoFiles($products);
    } else {
        // Если товары умещаются в один файл, сохраняем в основной и удаляем разбитые
        saveData('products', $products);
        
        $dataDir = __DIR__ . '/../data/';
        foreach (glob($dataDir . PRODUCTS_SPLIT_PREFIX . '*.json') as $file) {
            @unlink($file);
        }
        
        // Очищаем кэш разбитых файлов
        foreach (glob($dataDir . PRODUCTS_SPLIT_PREFIX . '*.json') as $file) {
            $basename = basename($file, '.json');
            unset($_dataCache[$basename]);
        }
    }
    
    // Очищаем кэш основного файла
    unset($_dataCache['products']);
}

?>
