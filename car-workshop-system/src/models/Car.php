<?php
/**
 * Car Model
 * Handles vehicle data operations for the car workshop system
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../config/AppConfig.php';

class Car extends BaseModel {
    protected $table = 'vehicles';
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Get all vehicles for a specific customer
     */
    public function getCustomerVehicles($customerId) {
        return $this->findAll(['customer_id' => $customerId], 'created_at DESC');
    }
    
    /**
     * Create a new vehicle
     */
    public function createVehicle($data) {
        try {
            // Check if serial number already exists
            if ($this->serialNumberExists($data['serial_number'])) {
                throw new Exception('Ο σειριακός αριθμός υπάρχει ήδη στο σύστημα');
            }
            
            $vehicleData = [
                'customer_id' => $data['customer_id'],
                'serial_number' => $data['serial_number'],
                'model' => $data['model'],
                'brand' => $data['brand'],
                'vehicle_type' => $data['vehicle_type'],
                'engine_type' => $data['engine_type'],
                'door_count' => $data['door_count'],
                'wheel_count' => $data['wheel_count'],
                'production_date' => $data['production_date'],
                'acquisition_year' => $data['acquisition_year']
            ];
            
            $vehicleId = $this->create($vehicleData);
            
            if ($vehicleId) {
                return [
                    'success' => true,
                    'vehicle_id' => $vehicleId,
                    'message' => 'Το όχημα δημιουργήθηκε επιτυχώς'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Αποτυχία δημιουργίας οχήματος'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Σφάλμα: ' . $e->getMessage()
            ];
        }
    }
    

    public function serialNumberExists($serialNumber) {
        return $this->exists(['serial_number' => $serialNumber]);
    }
    
    
    public function getVehicleTypes() {
        return array_keys(AppConfig::VEHICLE_TYPES);
    }
    
    
    public function getEngineTypes() {
        return array_keys(AppConfig::ENGINE_TYPES);
    }
    
    /**
     * Check what appointments will be affected by vehicle deletion
     */
    public function getVehicleDeletionImpact($vehicleId, $customerId = null) {
        // If customerId is provided, verify the vehicle belongs to the customer
        if ($customerId !== null) {
            $checkSql = "SELECT COUNT(*) FROM vehicles WHERE id = ? AND customer_id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$vehicleId, $customerId]);
            
            if ($checkStmt->fetchColumn() == 0) {
                throw new Exception('Δεν έχετε δικαίωμα προβολής αυτού του οχήματος');
            }
        } else {
            // For admin operations, just check if vehicle exists
            $checkSql = "SELECT COUNT(*) FROM vehicles WHERE id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$vehicleId]);
            
            if ($checkStmt->fetchColumn() == 0) {
                return ['exists' => false, 'total_appointments' => 0];
            }
        }
        
        // Get appointment statistics
        $sql = "SELECT 
                    COUNT(*) as total_appointments,
                    SUM(CASE WHEN appointment_date > CURDATE() THEN 1 ELSE 0 END) as future_appointments,
                    SUM(CASE WHEN status = 'ΑΚΥΡΩΜΕΝΟ' THEN 1 ELSE 0 END) as cancelled_appointments,
                    SUM(CASE WHEN status = 'ΠΕΡΑΙΩΜΕΝΟ' THEN 1 ELSE 0 END) as completed_appointments,
                    SUM(CASE WHEN status = 'ΣΕ ΕΞΕΛΙΞΗ' THEN 1 ELSE 0 END) as in_progress_appointments,
                    SUM(CASE WHEN status = 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ' AND appointment_date >= CURDATE() THEN 1 ELSE 0 END) as pending_appointments
                FROM appointments 
                WHERE vehicle_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$vehicleId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Add exists flag for admin operations
        $result['exists'] = true;
        
        return $result;
    }
    
    /**
     * Delete a vehicle from the database
     */
    public function deleteVehicle($vehicleId, $customerId) {
        // Verify the vehicle belongs to the customer for security
        if (!$this->exists(['id' => $vehicleId, 'customer_id' => $customerId])) {
            throw new Exception('Δεν έχετε δικαίωμα διαγραφής αυτού του οχήματος');
        }
        
        try {
            // Start transaction to ensure data consistency
            $this->beginTransaction();
            
            // Get appointment impact information for logging
            $impact = $this->getVehicleDeletionImpact($vehicleId, $customerId);
            
            // First, handle appointments that should be deleted (future appointments and cancelled ones)
            // Delete appointments that are:
            // 1. Future appointments (appointment_date > today)
            // 2. Cancelled appointments (regardless of date)
            // 3. Created appointments that haven't started yet
            $deleteAppointmentsSql = "DELETE FROM appointments 
                                     WHERE vehicle_id = ? 
                                     AND (
                                         appointment_date > CURDATE() 
                                         OR status = 'ΑΚΥΡΩΜΕΝΟ'
                                         OR (status = 'ΔΗΜΙΟΥΡΓΗΜΕΝΟ' AND appointment_date >= CURDATE())
                                     )";
            $deleteAppointmentsStmt = $this->db->prepare($deleteAppointmentsSql);
            $deleteAppointmentsStmt->execute([$vehicleId]);
            $deletedAppointments = $deleteAppointmentsStmt->rowCount();
            
            // For historical completed appointments, set vehicle_id to NULL to preserve history
            // but remove the vehicle association
            $preserveHistorySql = "UPDATE appointments 
                                  SET vehicle_id = NULL 
                                  WHERE vehicle_id = ? 
                                  AND status = 'ΠΕΡΑΙΩΜΕΝΟ'";
            $preserveHistoryStmt = $this->db->prepare($preserveHistorySql);
            $preserveHistoryStmt->execute([$vehicleId]);
            $preservedAppointments = $preserveHistoryStmt->rowCount();
            
        
           
            $cancelInProgressSql = "UPDATE appointments 
                                   SET status = 'ΑΚΥΡΩΜΕΝΟ', 
                                       cancelled_at = NOW(),
                                       vehicle_id = NULL
                                   WHERE vehicle_id = ? 
                                   AND status = 'ΣΕ ΕΞΕΛΙΞΗ'";
            $cancelInProgressStmt = $this->db->prepare($cancelInProgressSql);
            $cancelInProgressStmt->execute([$vehicleId]);
            $cancelledInProgress = $cancelInProgressStmt->rowCount();
            
            // Now delete the vehicle
            $result = $this->delete($vehicleId);
            
            // Commit the transaction
            $this->commit();
            
            // Return detailed result information
            return [
                'success' => $result,
                'deleted_appointments' => $deletedAppointments,
                'preserved_appointments' => $preservedAppointments,
                'cancelled_in_progress' => $cancelledInProgress,
                'total_impact' => $impact['total_appointments']
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->rollback();
            throw new Exception('Σφάλμα κατά τη διαγραφή του οχήματος: ' . $e->getMessage());
        }
    }
    
    /**
     * Get a specific vehicle by ID and customer ID
     */
    public function getVehicleById($vehicleId, $customerId) {
        $vehicle = $this->findById($vehicleId);
        // Check if vehicle belongs to the customer
        if ($vehicle && $vehicle['customer_id'] == $customerId) {
            return $vehicle;
        }
        return false;
    }
    
    
    public function deleteVehicleAdmin($vehicleId) {
        try {
            $this->beginTransaction();
            
            // Get impact analysis first (admin version - no customer check)
            $impact = $this->getVehicleDeletionImpact($vehicleId, null);
            
            if (!$impact['exists']) {
                $this->rollback();
                return ['success' => false, 'message' => 'Το όχημα δεν βρέθηκε'];
            }
            
            // Update appointments to remove vehicle reference (preserve history)
            $updateStmt = $this->db->prepare("
                UPDATE appointments 
                SET vehicle_id = NULL, updated_at = NOW() 
                WHERE vehicle_id = ? AND status NOT IN ('ΠΕΡΑΙΩΜΕΝΟ', 'ΑΚΥΡΩΜΕΝΟ')
            ");
            $updateStmt->execute([$vehicleId]);
            $affectedAppointments = $updateStmt->rowCount();
            
            // Delete the vehicle
            $result = $this->delete($vehicleId);
            
            if (!$result) {
                $this->rollback();
                return ['success' => false, 'message' => 'Αποτυχία διαγραφής οχήματος'];
            }
            
            $this->commit();
            
            return [
                'success' => true,
                'message' => 'Όχημα διαγράφηκε επιτυχώς από διαχειριστή',
                'affected_appointments' => $affectedAppointments,
                'total_appointments' => $impact['total_appointments']
            ];
            
        } catch (Exception $e) {
            $this->rollback();
            throw new Exception('Σφάλμα κατά τη διαγραφή του οχήματος: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all vehicles (for secretary management)
     */
    public function getAllVehicles() {
    $sql = "SELECT v.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email
        FROM vehicles v
        JOIN customers c ON v.customer_id = c.id
        JOIN users u ON c.user_id = u.id
        ORDER BY v.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Add vehicle 
     */
    public function addVehicle($data) {
        try {
            $result = $this->createVehicle($data);
            
            if ($result['success']) {
                // Get the created vehicle details
                $vehicleId = $result['vehicle_id'];
                $stmt = $this->db->prepare("
                    SELECT v.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as customer_name
                    FROM vehicles v
                    JOIN customers c ON v.customer_id = c.id
                    JOIN users u ON c.user_id = u.id
                    WHERE v.id = ?
                ");
                $stmt->execute([$vehicleId]);
                $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                return [
                    'success' => true,
                    'message' => $result['message'],
                    'vehicle' => $vehicle
                ];
            } else {
                return $result;
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to add vehicle: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update vehicle
     */
    public function updateVehicle($data) {
        try {
            $this->beginTransaction();
            
            // Validate required fields
            if (empty($data['id'])) {
                throw new Exception('Vehicle ID is required');
            }
            
            // Check if vehicle exists
            if (!$this->findById($data['id'])) {
                throw new Exception('Vehicle not found');
            }
            
            // Update vehicle using canonical schema fields
            $required = ['brand','model','vehicle_type','engine_type','door_count','wheel_count','production_date','acquisition_year'];
            foreach ($required as $field) {
                if (!array_key_exists($field, $data)) {
                    throw new Exception("Missing required field: {$field}");
                }
            }

            $updateData = [
                'brand' => $data['brand'],
                'model' => $data['model'], 
                'vehicle_type' => $data['vehicle_type'],
                'engine_type' => $data['engine_type'],
                'door_count' => (int)$data['door_count'],
                'wheel_count' => (int)$data['wheel_count'],
                'production_date' => $data['production_date'],
                'acquisition_year' => $data['acquisition_year']
            ];
            
            $result = $this->update($data['id'], $updateData);
            
            if ($result) {
                // Get updated vehicle details
                $stmt = $this->db->prepare("
                    SELECT v.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as customer_name
                    FROM vehicles v
                    JOIN customers c ON v.customer_id = c.id
                    JOIN users u ON c.user_id = u.id
                    WHERE v.id = ?
                ");
                $stmt->execute([$data['id']]);
                $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $this->commit();
                
                return [
                    'success' => true,
                    'message' => 'Το όχημα ενημερώθηκε επιτυχώς',
                    'vehicle' => $vehicle
                ];
            } else {
                throw new Exception('Failed to update vehicle');
            }
            
        } catch (Exception $e) {
            $this->rollback();
            return [
                'success' => false,
                'message' => 'Σφάλμα κατά την ενημέρωση του οχήματος: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete vehicle 
     */
    public function deleteVehicleById($vehicleId) {
        try {
            // Check if vehicle exists and get customer info
            $vehicle = $this->findById($vehicleId);
            
            if (!$vehicle) {
                return [
                    'success' => false,
                    'message' => 'Vehicle not found'
                ];
            }
            
            
            $result = $this->deleteVehicle($vehicleId, $vehicle['customer_id']);
            
            return $result;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete vehicle: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if serial number is already taken by another vehicle
     */
    public function isSerialNumberTaken($serialNumber, $excludeVehicleId = null) {
        $query = "SELECT id FROM vehicles WHERE serial_number = ?";
        $params = [$serialNumber];

        if ($excludeVehicleId !== null) {
            $query .= " AND id != ?";
            $params[] = $excludeVehicleId;
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }
}
?>