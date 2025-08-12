// 基础交互动画 - 第一优先级 javascript 实现

document.addeventlistener('domcontentloaded', function() {
    // 页面加载动画
    initpageloadanimations();
    
    // 导航和菜单动画
    initnavigationanimations();
    
    // 按钮和表单交互
    initbuttoninteractions();
    
    // 表单输入框动画
    initformanimations();
    
    // 加载状态指示器
    initloadingindicators();
});

/**
 * 页面加载动画初始化
 */
function initpageloadanimations() {
    // 为主要内容区域添加渐进式显示动画
    const animatedelements = [
        { selector: '.glass-effect', classname: 'fade-in-up', delay: 'delay-100' },
        { selector: 'h1', classname: 'fade-in-up', delay: 'delay-200' },
        { selector: '.text-base.md\:text-lg', classname: 'fade-in-up', delay: 'delay-300' },
        { selector: 'button.glass-effect', classname: 'fade-in-up', delay: 'delay-400' },
        { selector: '.grid.grid-cols-1.md\:grid-cols-2', classname: 'fade-in-up', delay: 'delay-500' }
    ];
    
    animatedelements.foreach(item => {
        const elements = document.queryselectorall(item.selector);
        elements.foreach((el, index) => {
            // 添加基础动画类
            el.classlist.add(item.classname);
            
            // 如果有延迟类，添加延迟
            if (item.delay) {
                // 为每个元素递增延迟
                const delaymultiplier = index * 100;
                el.style.animationdelay = (parseint(item.delay.replace('delay-', '')) * 100 + delaymultiplier) + 'ms';
            }
        });
    });
    
    // 为统计数据卡片添加数字递增动画
    initcounteranimations();
}

/**
 * 数字递增动画
 */
function initcounteranimations() {
    const counters = document.queryselectorall('.text-2xl.font-bold');
    
    counters.foreach(counter => {
        const targettext = counter.textcontent;
        const targetnumber = parseint(targettext.replace(/[^0-9]/g, ''));
        
        if (!isnan(targetnumber)) {
            let currentnumber = 0;
            const increment = targetnumber / 50; // 50步完成动画
            const duration = 2000; // 2秒
            const steptime = duration / 50;
            
            const updatecounter = () => {
                currentnumber += increment;
                if (currentnumber < targetnumber) {
                    counter.textcontent = math.floor(currentnumber).tostring();
                    settimeout(updatecounter, steptime);
                } else {
                    counter.textcontent = targettext;
                }
            };
            
            // 延迟启动数字动画
            settimeout(updatecounter, 1000);
        }
    });
}

/**
 * 导航和菜单动画初始化
 */
function initnavigationanimations() {
    const menubutton = document.getelementbyid('menubutton');
    const mobilemenu = document.getelementbyid('mobilemenu');
    const desktopmenu = document.getelementbyid('menu');
    
    if (menubutton && mobilemenu) {
        // 移动端菜单按钮点击事件
        menubutton.addeventlistener('click', function() {
            mobilemenu.classlist.toggle('active');
            
            // 添加按钮动画反馈
            this.style.transform = 'scale(0.95)';
            settimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
        
        // 点击菜单外部关闭菜单
        document.addeventlistener('click', function(event) {
            if (!menubutton.contains(event.target) && !mobilemenu.contains(event.target)) {
                mobilemenu.classlist.remove('active');
            }
        });
    }
    
    // 为导航链接添加悬停效果
    const navlinks = document.queryselectorall('nav a');
    navlinks.foreach(link => {
        link.classlist.add('link-hover');
    });
}

/**
 * 按钮和表单交互初始化
 */
function initbuttoninteractions() {
    // 为所有按钮添加悬停和点击效果
    const buttons = document.queryselectorall('button');
    
    buttons.foreach(button => {
        // 添加悬停效果类
        if (!button.classlist.contains('no-hover')) {
            button.classlist.add('btn-hover');
        }
        
        // 添加涟漪效果
        button.classlist.add('ripple');
        
        // 点击反馈
        button.addeventlistener('click', function(e) {
            // 创建涟漪效果
            createripple(e, this);
            
            // 按钮缩放反馈
            this.style.transform = 'scale(0.95)';
            settimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    });
    
    // 为卡片添加悬停效果
    const cards = document.queryselectorall('.glass-effect.rounded-lg');
    cards.foreach(card => {
        card.classlist.add('card-hover');
    });
    
    // 为图片添加悬停放大效果
    const imgcontainers = document.queryselectorall('.relative');
    imgcontainers.foreach(container => {
        const img = container.queryselector('img');
        if (img && !container.classlist.contains('no-zoom')) {
            container.classlist.add('img-hover-zoom');
        }
    });
}

/**
 * 表单输入框动画初始化
 */
function initformanimations() {
    const inputs = document.queryselectorall('input, textarea');
    
    inputs.foreach(input => {
        // 添加焦点动画类
        input.classlist.add('form-input');
        
        // 焦点事件
        input.addeventlistener('focus', function() {
            this.parentelement.classlist.add('focused');
        });
        
        input.addeventlistener('blur', function() {
            if (!this.value) {
                this.parentelement.classlist.remove('focused');
            }
        });
    });
}

/**
 * 加载状态指示器初始化
 */
function initloadingindicators() {
    // 为异步操作添加加载状态
    window.showloading = function(button) {
        const originalcontent = button.innerhtml;
        button.disabled = true;
        button.innerhtml = '<span class="loading-spinner"></span> 加载中...';
        
        return () => {
            button.disabled = false;
            button.innerhtml = originalcontent;
        };
    };
    
    // 为 ajax 请求全局添加加载状态
    const originalxhr = window.xmlhttprequest;
    window.xmlhttprequest = function() {
        const xhr = new originalxhr();
        const originalsend = xhr.send;
        
        xhr.send = function() {
            // 显示全局加载指示器
            const loader = document.createelement('div');
            loader.id = 'global-loader';
            loader.innerhtml = '<div class="loading-spinner"></div>';
            loader.style.csstext = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                padding: 10px;
                background: rgba(0, 0, 0, 0.8);
                border-radius: 5px;
            `;
            document.body.appendchild(loader);
            
            originalsend.apply(this, arguments);
            
            // 请求完成后移除加载指示器
            xhr.addeventlistener('loadend', function() {
                const loader = document.getelementbyid('global-loader');
                if (loader) {
                    loader.remove();
                }
            });
        };
        
        return xhr;
    };
}

/**
 * 创建涟漪效果
 */
function createripple(event, button) {
    const ripple = document.createelement('span');
    const rect = button.getboundingclientrect();
    const size = math.max(rect.width, rect.height);
    const x = event.clientx - rect.left - size / 2;
    const y = event.clienty - rect.top - size / 2;
    
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = x + 'px';
    ripple.style.top = y + 'px';
    ripple.classlist.add('ripple-effect');
    
    // 添加涟漪样式
    const style = document.createelement('style');
    style.textcontent = `
        .ripple-effect {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s ease-out;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendchild(style);
    
    button.appendchild(ripple);
    
    // 动画完成后移除涟漪元素
    settimeout(() => {
        ripple.remove();
    }, 600);
}

/**
 * 平滑滚动到指定元素
 */
function smoothscrollto(elementid) {
    const element = document.getelementbyid(elementid);
    if (element) {
        element.scrollintoview({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

/**
 * 添加骨架屏加载效果
 */
function showskeleton(containerselector, itemcount = 3) {
    const container = document.queryselector(containerselector);
    if (!container) return;
    
    // 清空容器
    container.innerhtml = '';
    
    // 创建骨架屏元素
    for (let i = 0; i < itemcount; i++) {
        const skeletonitem = document.createelement('div');
        skeletonitem.classlist.add('skeleton-item');
        skeletonitem.innerhtml = `
            <div class="skeleton" style="height: 200px; margin-bottom: 15px; border-radius: 8px;"></div>
            <div class="skeleton" style="height: 20px; width: 80%; margin-bottom: 10px; border-radius: 4px;"></div>
            <div class="skeleton" style="height: 16px; width: 60%; border-radius: 4px;"></div>
        `;
        container.appendchild(skeletonitem);
    }
}

/**
 * 移除骨架屏显示内容
 */
function removeskeleton(containerselector, content) {
    const container = document.queryselector(containerselector);
    if (!container) return;
    
    container.innerhtml = content;
    
    // 为新内容添加淡入动画
    const newelements = container.children;
    array.from(newelements).foreach((el, index) => {
        el.classlist.add('fade-in-up');
        el.style.animationdelay = (index * 100) + 'ms';
    });
}

// 导出函数供全局使用
window.animations = {
    smoothscrollto,
    showskeleton,
    removeskeleton,
    showloading
};