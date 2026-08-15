<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once __DIR__ . '/middleware.php';
$customerId=requireLogin();
$email=getCustomerEmail($customerId);
$action=$_GET['action']??'';
switch($action){case 'stats':getUserStats($email);break;case 'orders':getUserOrders($email);break;default:sendResponse(false,'Invalid action');}
function getUserStats($email){global $conn;$s=[];$st=$conn->prepare('SELECT COUNT(*) total FROM orders WHERE email=?');$st->bind_param('s',$email);$st->execute();$s['total_orders']=$st->get_result()->fetch_assoc()['total'];$st->close();$st=$conn->prepare("SELECT COUNT(*) total FROM orders WHERE email=? AND status='pending'");$st->bind_param('s',$email);$st->execute();$s['pending_orders']=$st->get_result()->fetch_assoc()['total'];$st->close();$st=$conn->prepare("SELECT COALESCE(SUM(total),0) spent FROM orders WHERE email=? AND status!='cancelled'");$st->bind_param('s',$email);$st->execute();$s['total_spent']=(float)$st->get_result()->fetch_assoc()['spent'];$st->close();sendResponse(true,'Stats retrieved',['stats'=>$s]);}
function getUserOrders($email){global $conn;$st=$conn->prepare('SELECT * FROM orders WHERE email=? ORDER BY created_at DESC');$st->bind_param('s',$email);$st->execute();$r=$st->get_result();$a=[];while($row=$r->fetch_assoc())$a[]=$row;sendResponse(true,'Orders retrieved',['orders'=>$a]);}
function getCustomerEmail($id){global $conn;$st=$conn->prepare('SELECT email FROM customers WHERE id=? LIMIT 1');$st->bind_param('i',$id);$st->execute();$r=$st->get_result();if(!$r->num_rows)sendResponse(false,'Customer account not found');return $r->fetch_assoc()['email'];}
function sendResponse($success,$message,$data=[]){echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data]);exit;}
?>