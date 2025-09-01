
docker exec -it orderlogix-rabbit rabbitmqctl add_vhost /orders
docker exec -it orderlogix-rabbit rabbitmqctl add_vhost /inventory
docker exec -it orderlogix-rabbit rabbitmqctl add_vhost /payments
docker exec -it orderlogix-rabbit rabbitmqctl add_vhost /shipping

docker exec -it orderlogix-rabbit rabbitmqctl add_user app_orders  "$env:RMQ_ORDERS_PASS"
docker exec -it orderlogix-rabbit rabbitmqctl add_user app_inventory "$env:RMQ_INV_PASS"
docker exec -it orderlogix-rabbit rabbitmqctl add_user app_payments "$env:RMQ_PAY_PASS"
docker exec -it orderlogix-rabbit rabbitmqctl add_user app_shipping "$env:RMQ_SHIP_PASS"

docker exec -it orderlogix-rabbit rabbitmqctl set_permissions -p /orders    app_orders    "^orders(\.|$)|^$" "^orders(\.|$)|^$" "^orders(\.|$)|^$"
docker exec -it orderlogix-rabbit rabbitmqctl set_permissions -p /inventory app_inventory "^inventory(\.|$)|^$" "^inventory(\.|$)|^$" "^inventory(\.|$)|^$"
docker exec -it orderlogix-rabbit rabbitmqctl set_permissions -p /payments  app_payments  "^payments(\.|$)|^$" "^payments(\.|$)|^$" "^payments(\.|$)|^$"
docker exec -it orderlogix-rabbit rabbitmqctl set_permissions -p /shipping  app_shipping  "^shipping(\.|$)|^$" "^shipping(\.|$)|^$" "^shipping(\.|$)|^$"


docker exec -it orderlogix-rabbit rabbitmqctl set_policy -p /inventory inv-quorum "^[a-z\.]*$" '{"queue-type":"quorum"}' --apply-to queues
docker exec -it orderlogix-rabbit rabbitmqctl set_policy -p /orders    orders-lazy ".*" '{"delivery-limit":5,"max-length-bytes":0,"overflow":"reject-publish"}' --apply-to queues
