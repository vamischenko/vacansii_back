FROM nginx:alpine

# Копируем конфигурацию nginx
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Создаем директорию для SSL сертификатов
RUN mkdir -p /etc/nginx/ssl

# Экспонируем порты
EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
