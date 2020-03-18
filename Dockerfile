FROM registry.cn-beijing.aliyuncs.com/dacall/service-base
COPY / /root/service/
RUN chmod 777 /root/service/start.sh
EXPOSE 80