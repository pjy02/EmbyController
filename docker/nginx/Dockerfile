FROM library/nginx as nginx

WORKDIR /web

ADD https://cdn.hongfs.cn/external/raw.githubusercontent.com/hongfs/hongfs/762cd92804b828d31d28c65e7fceecb45f61a245/php/release.conf /etc/nginx/templates/default.conf

COPY $PROJECT_DIR/ .

ENV WEB_ROOT=/web

EXPOSE 80
EXPOSE 443

CMD envsubst '${WEB_DOMAIN},${WEB_ROOT},${WEB_SSL_PORT},${WEB_SSL_PEM},${WEB_SSL_KEY},${WEB_FASTCGI}' < /etc/nginx/templates/default.conf > /etc/nginx/conf.d/default.conf && nginx -g 'daemon off;'
