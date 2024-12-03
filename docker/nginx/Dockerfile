FROM library/nginx as nginx

WORKDIR /web

COPY ./docker/nginx/nginx.conf /etc/nginx/templates/default.conf

COPY $PROJECT_DIR/ .

ENV WEB_ROOT=/web

EXPOSE 80
EXPOSE 443

CMD nginx -g 'daemon off;'
