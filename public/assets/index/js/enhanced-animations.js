/**
 * 第二优先级动画效果 - JavaScript增强版
 */

class EnhancedAnimations {
    constructor() {
        this.init();
    }

    init() {
        this.initCounterAnimation();
        this.initLazyLoading();
        this.initProgressBars();
        this.initChartAnimations();
        this.initNotificationAnimations();
        this.initTabAnimations();
        this.initListAnimations();
        this.initServerStatusAnimations();
        this.initMediaCardEffects();
    }

    // 数字递增动画
    initCounterAnimation() {
        const counters = document.querySelectorAll('.counter');
        
        const animateCounter = (counter) => {
            const target = parseInt(counter.getAttribute('data-target') || counter.textContent);
            const duration = 2000; // 2秒
            const step = target / (duration / 16); // 60fps
            let current = 0;
            
            counter.classList.add('counter-animate');
            
            const updateCounter = () => {
                current += step;
                if (current < target) {
                    counter.textContent = Math.floor(current).toLocaleString();
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.textContent = target.toLocaleString();
                }
            };
            
            updateCounter();
        };

        // 使用Intersection Observer来触发动画
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => observer.observe(counter));
    }

    // 图片懒加载动画
    initLazyLoading() {
        const lazyImages = document.querySelectorAll('img[loading="lazy"], .lazy-image');
        
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    
                    // 添加加载状态
                    img.classList.add('loading');
                    
                    // 如果图片有data-src属性，设置src
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                    }
                    
                    img.onload = () => {
                        img.classList.remove('loading');
                        img.classList.add('loaded');
                    };
                    
                    img.onerror = () => {
                        img.classList.remove('loading');
                        img.classList.add('error');
                    };
                    
                    imageObserver.unobserve(img);
                }
            });
        }, { threshold: 0.1 });

        lazyImages.forEach(img => imageObserver.observe(img));
    }

    // 进度条动画
    initProgressBars() {
        const progressBars = document.querySelectorAll('.progress-fill');
        
        const animateProgressBar = (progressBar) => {
            const targetWidth = progressBar.getAttribute('data-progress') || 
                              progressBar.style.width || 
                              progressBar.getAttribute('style')?.match(/width:\s*(\d+%)/)?.[1];
            
            if (targetWidth) {
                progressBar.style.setProperty('--progress-width', targetWidth);
                
                // 延迟一点以触发动画
                setTimeout(() => {
                    progressBar.classList.add('animated');
                }, 100);
            }
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateProgressBar(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        progressBars.forEach(bar => observer.observe(bar));
    }

    // 图表动画
    initChartAnimations() {
        const chartBars = document.querySelectorAll('.chart-bar');
        
        const animateChartBars = (container) => {
            const bars = container.querySelectorAll('.chart-bar');
            bars.forEach((bar, index) => {
                const targetHeight = bar.getAttribute('data-height') || bar.style.height;
                if (targetHeight) {
                    bar.style.setProperty('--bar-height', targetHeight);
                    
                    // 添加错开延迟
                    setTimeout(() => {
                        bar.classList.add('animated');
                    }, index * 100);
                }
            });
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateChartBars(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        // 观察图表容器
        document.querySelectorAll('.chart-container').forEach(container => {
            observer.observe(container);
        });
    }

    // 通知消息动画
    initNotificationAnimations() {
        // 创建通知容器
        let notificationContainer = document.getElementById('notification-container');
        if (!notificationContainer) {
            notificationContainer = document.createElement('div');
            notificationContainer.id = 'notification-container';
            notificationContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                pointer-events: none;
            `;
            document.body.appendChild(notificationContainer);
        }

        // 增强现有的rShowMessage函数
        if (typeof window.rShowMessage === 'function') {
            const originalShowMessage = window.rShowMessage;
            window.rShowMessage = function(message, type = 'info', direction = 'right', duration = 3000) {
                // 创建通知元素
                const notification = document.createElement('div');
                notification.className = `notification notification-${type} notification-enter`;
                notification.style.cssText = `
                    background: ${type === 0 ? '#10b981' : type === 1 ? '#ef4444' : '#3b82f6'};
                    color: white;
                    padding: 12px 20px;
                    border-radius: 8px;
                    margin-bottom: 10px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    pointer-events: auto;
                    max-width: 300px;
                    word-wrap: break-word;
                `;
                notification.textContent = message;
                
                notificationContainer.appendChild(notification);
                
                // 自动移除
                setTimeout(() => {
                    notification.classList.remove('notification-enter');
                    notification.classList.add('notification-exit');
                    
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 400);
                }, duration);
                
                // 调用原始函数
                originalShowMessage(message, type, direction, duration);
            };
        }
    }

    // 标签页切换动画
    initTabAnimations() {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetTab = button.getAttribute('data-tab');
                
                // 移除所有活动状态
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // 添加活动状态
                button.classList.add('active');
                const targetContent = document.querySelector(`.tab-content[data-tab="${targetTab}"]`);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });
    }

    // 列表项进入动画
    initListAnimations() {
        const animateListItems = (container) => {
            const items = container.querySelectorAll('.list-item');
            items.forEach((item, index) => {
                item.classList.add('list-item-enter');
                
                // 添加错开延迟类
                const delayClass = `list-item-stagger-${(index % 5) + 1}`;
                item.classList.add(delayClass);
            });
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateListItems(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        // 观察列表容器
        document.querySelectorAll('.list-container').forEach(container => {
            observer.observe(container);
        });

        // 为动态添加的列表项提供动画
        const observeDynamicLists = () => {
            document.querySelectorAll('.list-container:not(.observed)').forEach(container => {
                container.classList.add('observed');
                observer.observe(container);
            });
        };

        // 定期检查新的列表容器
        setInterval(observeDynamicLists, 1000);
    }

    // 服务器状态动画
    initServerStatusAnimations() {
        const updateServerStatus = (element, isOnline) => {
            const indicator = element.querySelector('.status-indicator');
            if (indicator) {
                indicator.classList.remove('online', 'offline');
                indicator.classList.add(isOnline ? 'online' : 'offline');
            }
        };

        // 监听服务器状态变化
        document.addEventListener('serverStatusChange', (event) => {
            const { element, isOnline } = event.detail;
            updateServerStatus(element, isOnline);
        });
    }

    // 媒体卡片增强效果
    initMediaCardEffects() {
        const mediaCards = document.querySelectorAll('.media-card');
        
        mediaCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0) scale(1)';
            });
        });
    }
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', () => {
    window.enhancedAnimations = new EnhancedAnimations();
});

// 提供全局函数供其他脚本使用
window.showEnhancedNotification = (message, type = 'info', duration = 3000) => {
    if (window.enhancedAnimations) {
        window.enhancedAnimations.showNotification(message, type, duration);
    }
};

window.animateCounter = (element, targetValue) => {
    if (window.enhancedAnimations) {
        window.enhancedAnimations.animateCounter(element, targetValue);
    }
};

window.animateProgressBar = (element, targetWidth) => {
    if (window.enhancedAnimations) {
        window.enhancedAnimations.animateProgressBar(element, targetWidth);
    }
};