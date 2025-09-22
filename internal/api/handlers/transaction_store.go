package handlers

import (
	"sync"
	"time"
)

// TransactionStore holds all transactions in memory
type TransactionStore struct {
	transactions []Transaction
	mutex        sync.RWMutex
}

// Global transaction store instance
var globalTransactionStore = &TransactionStore{
	transactions: make([]Transaction, 0),
}

// AddTransaction adds a new transaction to the store
func (ts *TransactionStore) AddTransaction(transaction Transaction) {
	ts.mutex.Lock()
	defer ts.mutex.Unlock()
	
	// Add timestamp if not provided
	if transaction.Timestamp == "" {
		transaction.Timestamp = time.Now().UTC().Format(time.RFC3339)
	}
	
	// Add to beginning of slice (newest first)
	ts.transactions = append([]Transaction{transaction}, ts.transactions...)
	
	// Keep only the last 100 transactions to prevent memory issues
	if len(ts.transactions) > 100 {
		ts.transactions = ts.transactions[:100]
	}
}

// GetTransactions returns all transactions
func (ts *TransactionStore) GetTransactions() []Transaction {
	ts.mutex.RLock()
	defer ts.mutex.RUnlock()
	
	// Return a copy to prevent external modification
	result := make([]Transaction, len(ts.transactions))
	copy(result, ts.transactions)
	return result
}

// GetTransactionStore returns the global transaction store instance
func GetTransactionStore() *TransactionStore {
	return globalTransactionStore
}