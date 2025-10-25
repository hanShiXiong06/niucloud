const fs = require('fs')
const path = require('path')

/**
 * 可配置的构建发布脚本
 * 从 build.config.json 读取配置
 */
const publish = () => {
    // 读取配置文件
    let config = {
        outputPath: '../niucloud/public',
        outputName: 'admin'
    }

    try {
        const configFile = path.join(__dirname, 'build.config.json')
        if (fs.existsSync(configFile)) {
            const configData = fs.readFileSync(configFile, 'utf-8')
            config = JSON.parse(configData)
            console.log('✓ 已加载配置文件:', configFile)
            console.log('  输出路径:', config.outputPath)
            console.log('  输出名称:', config.outputName)
        } else {
            console.log('⚠ 未找到配置文件，使用默认配置')
        }
    } catch (e) {
        console.error('⚠ 读取配置文件失败，使用默认配置:', e.message)
    }

    const src = './dist'
    const dest = path.join(config.outputPath, config.outputName)

    // 处理 index.html 中的资源路径
    solve(config.outputName)

    // 目标目录不存在停止复制
    try {
        // 确保输出目录存在
        if (!fs.existsSync(config.outputPath)) {
            console.log('✓ 创建输出目录:', config.outputPath)
            fs.mkdirSync(config.outputPath, { recursive: true })
        }
    } catch (e) {
        console.error('✗ 创建输出目录失败:', e.message)
        return
    }

    // 删除目标目录下文件
    if (fs.existsSync(dest)) {
        console.log('✓ 删除旧文件:', dest)
        fs.rm(dest, { recursive: true }, err => {
            if(err) {
                console.error('✗ 删除失败:', err)
                return
            }

            copyFiles(src, dest)
        })
    } else {
        copyFiles(src, dest)
    }
}

const copyFiles = (src, dest) => {
    console.log('✓ 复制文件: ' + src + ' -> ' + dest)
    fs.cp(src, dest, { recursive: true }, (err) => {
        if (err) {
            console.error('✗ 复制失败:', err)
        } else {
            console.log('✓ 构建完成！')
        }
    })
}

const solve = (outputName) => {
    const fn = './dist/index.html'
    
    if (!fs.existsSync(fn)) {
        console.error('✗ 未找到 index.html 文件')
        return
    }

    const fc = fs.readFileSync(fn, 'utf-8')
    let text = new String(fc)
    
    // 替换资源路径
    text = text.replaceAll('./assets/', `/${outputName}/assets/`)
    text = text.replace('./niucloud.ico', `/${outputName}/niucloud.ico`)
    
    fs.writeFileSync(fn, text, 'utf8')
    console.log('✓ 已处理 index.html 资源路径')
}

publish()

