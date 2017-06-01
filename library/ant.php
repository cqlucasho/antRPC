<?php
# 根目录
define('ANT_ROOT_PATH', dirname(__DIR__).DIRECTORY_SEPARATOR);

# 其它信息
define('ANT_PACKAGE_SEPARATOR', '.');
define('ANT_FILE_EXT', '.php');

/**
 * Ant核心方法类
 *
 * @author lucasho
 * @created 2017-01-18
 * @modified 2017-01-18
 * @version 1.0
 * @link http://github.com/cqlucasho
 */
class Ant {
    /**
     * 根据$file和$ext参数查找并引用文件并返回文件返回值，如果文件已引用将直接返回值。
     * @example
     *  <pre>
     *      Ant::import('kernels.caches.engines.file_cache_engine');
     *  </pre>
     *
     * @param string $file  完整类名字，允许使用包分隔符。
     * @param string $ext   文件扩展名，默认为ANT_FILE_EXT。
     *
     * @return mixed 返回引用文件中的返回值。
     * @throws Exception 如果文件没有找到引发异常。
     */
    public static function import($file, $ext = ANT_FILE_EXT) {
        # 生成文件缓存key码。
        $fileKey = md5($file . $ext);
        $fileFullPath = ANT_ROOT_PATH.self::_realFile($file).$ext;

        if (!isset(self::$_files[$fileKey])) {
            if (is_file($fileFullPath)) {
                return (self::$_files[$fileKey] = include($fileFullPath));
            }

            throw new Exception("Ant::import not found file - '{$fileFullPath}' in import.");
        }

        return self::$_files[$fileKey];
    }

    /**
     * 根据$file获取真实的文件信息。
     *
     * @param string $file 文件名称，允许使用ANT_PACKAGE_SEPARATOR常量。
     * @return string
     */
    protected static function _realFile($file) {
        return str_replace(ANT_PACKAGE_SEPARATOR, DIRECTORY_SEPARATOR, $file);
    }

    /**
     * 已加载文件
     * @var array $_files
     */
    protected static $_files = array();
}